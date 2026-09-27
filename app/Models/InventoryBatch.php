<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\StorageType;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A received lot of one inventory item. Quantities and statuses are changed
 * exclusively by InventoryService so every change leaves a movement record.
 */
#[Fillable([
    'supplier_id', 'purchase_order_item_id', 'batch_number', 'lot_number', 'initial_quantity', 'quantity', 'unit_cost',
    'manufactured_at', 'expires_at', 'received_at', 'storage_type', 'location', 'status', 'status_reason',
])]
class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'integer',
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'received_at' => 'date',
            'storage_type' => StorageType::class,
            'status' => BatchStatus::class,
            'last_alert_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * First calendar day on which a batch may still be sold, honouring the
     * configured minimum remaining shelf life.
     */
    public static function earliestSellableExpiry(): Carbon
    {
        return local_today()->addDays(max(0, (int) settings('inventory.min_sellable_shelf_life_days', 1)));
    }

    /**
     * Batches that may be allocated to new orders. Expiry is checked by date,
     * not only by status, so a batch that expired since the last scheduled
     * status sweep can never be sold.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeAllocatable(Builder $query): void
    {
        $query->where('inventory_batches.status', BatchStatus::Available)
            ->where('inventory_batches.quantity', '>', 0)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('inventory_batches.expires_at')
                ->orWhereDate('inventory_batches.expires_at', '>=', self::earliestSellableExpiry()->toDateString()));
    }

    /**
     * First-expired-first-out, then first-received-first-out. Batches without
     * an expiry date are used last.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeAllocationOrder(Builder $query): void
    {
        $query->orderByRaw('case when inventory_batches.expires_at is null then 1 else 0 end')
            ->orderBy('inventory_batches.expires_at')
            ->orderBy('inventory_batches.received_at')
            ->orderBy('inventory_batches.id');
    }

    /**
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->whereIn('inventory_batches.status', BatchStatus::onHand())->where('inventory_batches.quantity', '>', 0);
    }

    /**
     * Units still held whose expiry date has passed.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->inStock()->whereDate('inventory_batches.expires_at', '<', local_today()->toDateString());
    }

    /**
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->inStock()
            ->where('inventory_batches.status', '!=', BatchStatus::Expired)
            ->whereDate('inventory_batches.expires_at', '>=', local_today()->toDateString())
            ->whereDate('inventory_batches.expires_at', '<=', local_today()->addDays($days)->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->daysUntilExpiry() < 0;
    }

    public function isAllocatable(): bool
    {
        return $this->status === BatchStatus::Available
            && $this->quantity > 0
            && ($this->expires_at === null || $this->expires_at->toDateString() >= self::earliestSellableExpiry()->toDateString());
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        $expiry = Carbon::parse($this->expires_at->toDateString(), config('app.display_timezone'));

        return (int) round(local_today()->diffInDays($expiry, false));
    }
}
