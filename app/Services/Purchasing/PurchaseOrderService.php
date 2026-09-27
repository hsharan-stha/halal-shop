<?php

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\Inventory\InventoryException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use LogicException;

class PurchaseOrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create or update a draft. Line totals and the subtotal are always
     * calculated here from quantity × unit cost.
     *
     * @param  array{supplier_id: int, expected_at: ?string, notes: ?string}  $data
     * @param  list<array{product_variant_id: int, quantity: int, unit_cost: int}>  $lines
     */
    public function save(PurchaseOrder $order, array $data, array $lines, User $actor): PurchaseOrder
    {
        if ($order->exists && ! $order->status->isEditable()) {
            throw new LogicException('Only draft purchase orders can be edited.');
        }

        return DB::transaction(function () use ($order, $data, $lines, $actor): PurchaseOrder {
            $order->fill($data);

            if (! $order->exists) {
                $order->forceFill(['status' => PurchaseOrderStatus::Draft, 'created_by' => $actor->id]);
            }

            $order->subtotal = array_sum(array_map(fn (array $line) => $line['quantity'] * $line['unit_cost'], $lines));
            $order->save();

            if ($order->order_number === null) {
                $order->forceFill(['order_number' => 'PO-'.local_today()->format('Ymd').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }

            $order->items()->delete();
            $order->items()->createMany($lines);

            return $order;
        });
    }

    public function markOrdered(PurchaseOrder $order, User $actor): void
    {
        $this->transition($order, PurchaseOrderStatus::Draft, function (PurchaseOrder $locked): void {
            if (! $locked->items()->exists()) {
                throw InventoryException::invalidQuantity();
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()])->save();
        }, 'purchase_order.ordered', $actor);
    }

    public function cancel(PurchaseOrder $order, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $actor, $reason): void {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isCancellable()) {
                throw new LogicException('Purchase order cannot be cancelled in its current status.');
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Cancelled, 'cancelled_at' => now()])->save();
            $this->auditLogger->log('purchase_order.cancelled', $locked, null, ['reason' => $reason], $actor->id);
        });
    }

    public function delete(PurchaseOrder $order): void
    {
        if (! $order->status->isEditable()) {
            throw new LogicException('Only draft purchase orders can be deleted.');
        }

        $order->delete();
    }

    /**
     * Book delivered goods into stock. Each received line becomes a batch with
     * a PURCHASE movement referencing this order.
     *
     * @param  array<int, array{quantity: int, batch_number?: ?string, lot_number?: ?string, expires_at?: ?string, manufactured_at?: ?string, location?: ?string}>  $receipts  keyed by purchase order item id
     * @return int units received
     */
    public function receive(PurchaseOrder $order, array $receipts, User $actor): int
    {
        return DB::transaction(function () use ($order, $receipts, $actor): int {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isReceivable()) {
                throw new LogicException('Purchase order is not awaiting delivery.');
            }

            $lines = $locked->items()->with('variant')->get()->keyBy('id');
            $received = 0;

            foreach ($receipts as $lineId => $receipt) {
                $quantity = (int) ($receipt['quantity'] ?? 0);

                if ($quantity === 0) {
                    continue;
                }

                /** @var PurchaseOrderItem|null $line */
                $line = $lines->get($lineId);

                if (! $line || $quantity < 0 || $quantity > $line->remainingQuantity()) {
                    throw InventoryException::invalidQuantity();
                }

                $this->inventory->receive(
                    $this->inventory->itemFor($line->variant),
                    $quantity,
                    [
                        'batch_number' => $receipt['batch_number'] ?? null,
                        'lot_number' => $receipt['lot_number'] ?? null,
                        'expires_at' => $receipt['expires_at'] ?? null,
                        'manufactured_at' => $receipt['manufactured_at'] ?? null,
                        'location' => $receipt['location'] ?? null,
                        'unit_cost' => $line->unit_cost,
                        'supplier_id' => $locked->supplier_id,
                        'purchase_order_item_id' => $line->id,
                    ],
                    $locked,
                    $actor,
                );

                $line->increment('quantity_received', $quantity);
                $received += $quantity;
            }

            if ($received === 0) {
                throw InventoryException::invalidQuantity();
            }

            $complete = $lines->every(fn (PurchaseOrderItem $line) => $line->quantity_received >= $line->quantity);
            $locked->forceFill([
                'status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $complete ? now() : null,
            ])->save();

            $this->auditLogger->log('purchase_order.received', $locked, null, ['units' => $received], $actor->id);

            return $received;
        });
    }

    private function transition(PurchaseOrder $order, PurchaseOrderStatus $from, callable $apply, string $action, User $actor): void
    {
        DB::transaction(function () use ($order, $from, $apply, $action, $actor): void {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== $from) {
                throw new LogicException('Purchase order status changed; reload and try again.');
            }

            $apply($locked);
            $this->auditLogger->log($action, $locked, ['status' => $from->value], ['status' => $locked->status->value], $actor->id);
        });
    }
}
