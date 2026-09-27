<?php

namespace App\Services\Inventory;

use App\Enums\BatchStatus;
use App\Enums\InventoryMovementType;
use App\Exceptions\Inventory\InventoryException;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LocaleService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only place stock quantities change. Every operation runs in a database
 * transaction that locks the inventory item row first (then its batches), so
 * concurrent operations on the same item are serialised and cannot oversell.
 */
class InventoryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LocaleService $locales,
    ) {}

    public function itemFor(ProductVariant $variant): InventoryItem
    {
        return $variant->inventoryItem()->firstOrCreate();
    }

    public function availableQuantity(ProductVariant $variant): int
    {
        return $this->itemFor($variant)->sellableQuantity();
    }

    /**
     * Add stock. Batch-tracked items get a new batch; stock that has already
     * expired is refused.
     *
     * @param  array{batch_number?: ?string, lot_number?: ?string, expires_at?: CarbonInterface|string|null, manufactured_at?: CarbonInterface|string|null, received_at?: CarbonInterface|string|null, unit_cost?: ?int, location?: ?string, supplier_id?: ?int, purchase_order_item_id?: ?int, storage_type?: mixed, status?: BatchStatus}  $batch
     */
    public function receive(
        InventoryItem $item,
        int $quantity,
        array $batch = [],
        ?Model $reference = null,
        ?User $actor = null,
        ?string $reason = null,
        InventoryMovementType $type = InventoryMovementType::Purchase,
    ): ?InventoryBatch {
        if ($quantity < 1) {
            throw InventoryException::invalidQuantity();
        }

        return DB::transaction(function () use ($item, $quantity, $batch, $reference, $actor, $reason, $type): ?InventoryBatch {
            $item = $this->lock($item);

            if (! $item->track_batches) {
                $this->record($item, $type, $quantity, null, $reference, $actor, $reason);

                return null;
            }

            $expiresAt = $this->date($batch['expires_at'] ?? null);

            if ($expiresAt && $expiresAt->lt(local_today())) {
                throw InventoryException::expiredOnReceipt();
            }

            $created = $item->batches()->create([
                'supplier_id' => $batch['supplier_id'] ?? null,
                'purchase_order_item_id' => $batch['purchase_order_item_id'] ?? null,
                'batch_number' => filled($batch['batch_number'] ?? null) ? Str::upper(trim($batch['batch_number'])) : $this->generateBatchNumber(),
                'lot_number' => filled($batch['lot_number'] ?? null) ? trim($batch['lot_number']) : null,
                'initial_quantity' => $quantity,
                'quantity' => 0,
                'unit_cost' => $batch['unit_cost'] ?? null,
                'manufactured_at' => $this->date($batch['manufactured_at'] ?? null),
                'expires_at' => $expiresAt,
                'received_at' => $this->date($batch['received_at'] ?? null) ?? local_today(),
                'storage_type' => $batch['storage_type'] ?? $item->variant()->with('product:id,storage_type')->first()?->product?->storage_type,
                'location' => filled($batch['location'] ?? null) ? trim($batch['location']) : $item->location,
                'status' => $batch['status'] ?? BatchStatus::Available,
            ]);

            $this->record($item, $type, $quantity, $created, $reference, $actor, $reason);

            return $created;
        });
    }

    /**
     * Correct a stock count (stocktake, found/missing units). Batch-tracked
     * items must be adjusted per batch.
     */
    public function adjust(InventoryItem $item, int $delta, string $reason, ?InventoryBatch $batch = null, ?User $actor = null): void
    {
        if ($delta === 0) {
            throw InventoryException::invalidQuantity();
        }

        DB::transaction(function () use ($item, $delta, $reason, $batch, $actor): void {
            $item = $this->lock($item);

            if ($item->track_batches) {
                if (! $batch) {
                    throw InventoryException::batchRequired();
                }

                $batch = $this->lockBatch($item, $batch);

                if ($batch->status === BatchStatus::Disposed || $batch->quantity + $delta < 0) {
                    throw $batch->status === BatchStatus::Disposed ? InventoryException::batchNotAvailable() : InventoryException::negativeStock();
                }
            } elseif ($item->quantity_on_hand + $delta < 0 && ! settings('inventory.allow_negative_stock')) {
                throw InventoryException::negativeStock();
            }

            $this->record($item, InventoryMovementType::Adjustment, $delta, $item->track_batches ? $batch : null, null, $actor, $reason);
        });
    }

    /**
     * Write stock off (damaged or expired). A batch emptied this way is marked disposed.
     */
    public function dispose(InventoryBatch $batch, int $quantity, InventoryMovementType $type, string $reason, ?User $actor = null): void
    {
        if (! in_array($type, InventoryMovementType::disposals(), true)) {
            throw InventoryException::invalidQuantity();
        }

        DB::transaction(function () use ($batch, $quantity, $type, $reason, $actor): void {
            $item = $this->lock($batch->inventory_item_id);
            $batch = $this->lockBatch($item, $batch);

            if ($quantity < 1 || $quantity > $batch->quantity) {
                throw InventoryException::invalidQuantity();
            }

            $this->record($item, $type, -$quantity, $batch, null, $actor, $reason);

            if ($batch->quantity === 0) {
                $batch->forceFill(['status' => BatchStatus::Disposed, 'status_reason' => Str::limit($reason, 250, '')])->save();
            }
        });
    }

    /**
     * Move units to another storage location. A partial transfer splits the
     * batch so lot and expiry information stays with the units.
     */
    public function transfer(InventoryBatch $batch, int $quantity, string $location, ?string $reason = null, ?User $actor = null): InventoryBatch
    {
        $location = trim($location);

        return DB::transaction(function () use ($batch, $quantity, $location, $reason, $actor): InventoryBatch {
            $item = $this->lock($batch->inventory_item_id);
            $batch = $this->lockBatch($item, $batch);

            if ($quantity < 1 || $quantity > $batch->quantity || $location === '' || $location === $batch->location) {
                throw InventoryException::invalidQuantity();
            }

            if ($batch->status === BatchStatus::Disposed) {
                throw InventoryException::batchNotAvailable();
            }

            $reason = trim(($reason ?? '').' '.$this->systemNote('admin.inventory.transfer_note', ['from' => $batch->location ?: '—', 'to' => $location]));

            if ($quantity === $batch->quantity) {
                $this->record($item, InventoryMovementType::Transfer, -$quantity, $batch, null, $actor, $reason);
                $batch->forceFill(['location' => $location])->save();
                $this->record($item, InventoryMovementType::Transfer, $quantity, $batch, null, $actor, $reason);

                return $batch;
            }

            $destination = $batch->replicate(['quantity', 'initial_quantity', 'location', 'last_alert_days']);
            $destination->forceFill(['quantity' => 0, 'initial_quantity' => $quantity, 'location' => $location])->save();

            $this->record($item, InventoryMovementType::Transfer, -$quantity, $batch, null, $actor, $reason);
            $this->record($item, InventoryMovementType::Transfer, $quantity, $destination, null, $actor, $reason);

            return $destination;
        });
    }

    /**
     * Quarantine / release / mark expired. Disposal goes through dispose().
     */
    public function changeBatchStatus(InventoryBatch $batch, BatchStatus $status, ?string $reason = null, ?User $actor = null): void
    {
        DB::transaction(function () use ($batch, $status, $reason, $actor): void {
            $item = $this->lock($batch->inventory_item_id);
            $batch = $this->lockBatch($item, $batch);
            $previous = $batch->status;

            if ($status === BatchStatus::Disposed || $previous === BatchStatus::Disposed || $status === $previous) {
                throw InventoryException::batchNotAvailable();
            }

            if ($status === BatchStatus::Available && $batch->isExpired()) {
                throw InventoryException::cannotReleaseExpired();
            }

            $batch->forceFill(['status' => $status, 'status_reason' => $reason ? Str::limit($reason, 250, '') : null])->save();

            $this->auditLogger->log('inventory_batch.status_changed', $batch, ['status' => $previous->value], ['status' => $status->value, 'reason' => $reason], $actor?->id);
        });
    }

    /**
     * Take stock for an order, oldest-expiring valid batches first. Expired,
     * quarantined and short-dated batches are never used. Either the full
     * quantity is allocated or nothing changes.
     *
     * @return list<array{batch_id: int|null, quantity: int}>
     */
    public function allocate(ProductVariant $variant, int $quantity, Model $reference, ?User $actor = null): array
    {
        if ($quantity < 1) {
            throw InventoryException::invalidQuantity();
        }

        return DB::transaction(function () use ($variant, $quantity, $reference, $actor): array {
            $item = $this->lock($this->itemFor($variant));

            if (! $item->track_batches) {
                if ($item->quantity_on_hand < $quantity && ! settings('inventory.allow_negative_stock')) {
                    throw InventoryException::insufficientStock($quantity, max(0, $item->quantity_on_hand));
                }

                $this->record($item, InventoryMovementType::Sale, -$quantity, null, $reference, $actor);

                return [['batch_id' => null, 'quantity' => $quantity]];
            }

            $batches = $item->batches()->allocatable()->allocationOrder()->lockForUpdate()->get();
            $available = (int) $batches->sum('quantity');

            if ($available < $quantity) {
                throw InventoryException::insufficientStock($quantity, $available);
            }

            $allocations = [];
            $remaining = $quantity;

            foreach ($batches as $batch) {
                $take = min($remaining, $batch->quantity);
                $this->record($item, InventoryMovementType::Sale, -$take, $batch, $reference, $actor);
                $allocations[] = ['batch_id' => $batch->id, 'quantity' => $take];
                $remaining -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            return $allocations;
        });
    }

    /**
     * Put previously allocated stock back. Cancelled (unshipped) orders return
     * units to their original batch; customer returns go into a separate
     * quarantined batch for inspection and are never resold automatically.
     *
     * @param  list<array{batch_id: int|null, quantity: int}>  $allocations
     */
    public function release(ProductVariant $variant, array $allocations, Model $reference, InventoryMovementType $type = InventoryMovementType::CancelledOrder, ?User $actor = null): void
    {
        if (! in_array($type, [InventoryMovementType::CancelledOrder, InventoryMovementType::Return], true)) {
            throw InventoryException::invalidQuantity();
        }

        DB::transaction(function () use ($variant, $allocations, $reference, $type, $actor): void {
            $item = $this->lock($this->itemFor($variant));

            foreach ($allocations as $allocation) {
                $quantity = (int) $allocation['quantity'];

                if ($quantity < 1) {
                    throw InventoryException::invalidQuantity();
                }

                if (! $item->track_batches || $allocation['batch_id'] === null) {
                    $this->record($item, $type, $quantity, null, $reference, $actor);

                    continue;
                }

                $batch = $this->lockBatch($item, $item->batches()->findOrFail($allocation['batch_id']));

                if ($type === InventoryMovementType::Return) {
                    $batch = $batch->replicate(['quantity', 'initial_quantity', 'last_alert_days']);
                    $batch->forceFill([
                        'quantity' => 0,
                        'initial_quantity' => $quantity,
                        'received_at' => local_today(),
                        'status' => BatchStatus::Quarantined,
                        'status_reason' => $this->systemNote('admin.inventory.customer_return'),
                    ])->save();
                } elseif ($batch->status === BatchStatus::Disposed) {
                    $batch->forceFill(['status' => BatchStatus::Quarantined, 'status_reason' => $this->systemNote('admin.inventory.restocked_after_disposal')])->save();
                }

                $this->record($item, $type, $quantity, $batch, $reference, $actor);
            }
        });
    }

    /**
     * Flag batches whose expiry date has passed. Quantities are untouched;
     * staff write the units off with dispose().
     */
    public function markExpiredBatches(): int
    {
        return InventoryBatch::query()
            ->whereIn('status', [BatchStatus::Available, BatchStatus::Quarantined])
            ->whereDate('expires_at', '<', local_today()->toDateString())
            ->update(['status' => BatchStatus::Expired->value, 'status_reason' => $this->systemNote('admin.inventory.auto_expired')]);
    }

    /**
     * Batch tracking can only be switched while the item holds no stock, so
     * existing units never lose (or gain) batch/expiry information.
     */
    public function setBatchTracking(InventoryItem $item, bool $trackBatches): void
    {
        DB::transaction(function () use ($item, $trackBatches): void {
            $item = $this->lock($item);

            if ($item->track_batches === $trackBatches) {
                return;
            }

            if ($item->quantity_on_hand !== 0) {
                throw InventoryException::trackingLocked();
            }

            $item->forceFill(['track_batches' => $trackBatches])->save();
            $this->auditLogger->log('inventory_item.tracking_changed', $item, null, ['track_batches' => $trackBatches]);
        });
    }

    private function lock(InventoryItem|int $item): InventoryItem
    {
        return InventoryItem::query()->whereKey($item instanceof InventoryItem ? $item->getKey() : $item)->lockForUpdate()->firstOrFail();
    }

    private function lockBatch(InventoryItem $item, InventoryBatch $batch): InventoryBatch
    {
        return $item->batches()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Apply a signed change to the (locked) item and batch and write the ledger entry.
     */
    private function record(
        InventoryItem $item,
        InventoryMovementType $type,
        int $delta,
        ?InventoryBatch $batch,
        ?Model $reference,
        ?User $actor,
        ?string $reason = null,
    ): InventoryMovement {
        $before = $item->quantity_on_hand;
        $batchBefore = $batch?->quantity;

        if ($batch) {
            $batch->forceFill(['quantity' => $batch->quantity + $delta])->save();
        }

        $item->forceFill(['quantity_on_hand' => $before + $delta])->save();

        return $item->movements()->create([
            'inventory_batch_id' => $batch?->id,
            'type' => $type,
            'quantity' => $delta,
            'before_quantity' => $before,
            'after_quantity' => $item->quantity_on_hand,
            'batch_before_quantity' => $batchBefore,
            'batch_after_quantity' => $batch?->quantity,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $actor?->id ?? Auth::id(),
            'reason' => $reason !== null && $reason !== '' ? Str::limit($reason, 250, '') : null,
        ]);
    }

    private function date(CarbonInterface|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value instanceof CarbonInterface ? $value->toDateString() : $value, config('app.display_timezone'))->startOfDay();
    }

    /**
     * System-written ledger notes are stored in the store's primary locale so
     * the history reads consistently whichever language the operator uses.
     *
     * @param  array<string, string>  $replace
     */
    private function systemNote(string $key, array $replace = []): string
    {
        return __($key, $replace, $this->locales->default());
    }

    private function generateBatchNumber(): string
    {
        return 'B'.local_today()->format('ymd').'-'.Str::upper(Str::random(5));
    }
}
