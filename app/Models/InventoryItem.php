<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock record for one product variant. `quantity_on_hand` counts every unit
 * physically held (including quarantined/expired batches awaiting disposal);
 * the sellable quantity is derived from allocatable batches at query time so
 * that stock never becomes sellable after it has expired.
 *
 * All quantity changes must go through InventoryService.
 */
#[Fillable(['low_stock_threshold', 'location'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'track_batches' => 'boolean',
            'quantity_on_hand' => 'integer',
            'low_stock_threshold' => 'integer',
            'low_stock_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * @return HasMany<InventoryBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function lowStockThreshold(): int
    {
        return $this->low_stock_threshold ?? (int) settings('inventory.low_stock_threshold', 5);
    }

    /**
     * Units that may be allocated to new orders right now.
     */
    public function sellableQuantity(): int
    {
        if (array_key_exists('sellable_quantity', $this->attributes)) {
            return max(0, (int) $this->attributes['sellable_quantity']);
        }

        if (! $this->track_batches) {
            return max(0, $this->quantity_on_hand);
        }

        return (int) $this->batches()->allocatable()->sum('quantity');
    }

    public function stockStatus(): StockStatus
    {
        return StockStatus::for($this->sellableQuantity(), $this->lowStockThreshold());
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeWithSellableQuantity(Builder $query): void
    {
        [$sql, $bindings] = self::sellableExpression();

        if ($query->getQuery()->columns === null) {
            $query->select('inventory_items.*');
        }

        $query->selectRaw("({$sql}) as sellable_quantity", $bindings);
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeWhereStockStatus(Builder $query, StockStatus $status): void
    {
        [$sql, $bindings] = self::sellableExpression();
        $threshold = 'coalesce(inventory_items.low_stock_threshold, ?)';
        $default = (int) settings('inventory.low_stock_threshold', 5);

        match ($status) {
            StockStatus::OutOfStock => $query->whereRaw("({$sql}) <= 0", $bindings),
            StockStatus::LowStock => $query->whereRaw("({$sql}) > 0", $bindings)->whereRaw("({$sql}) <= {$threshold}", [...$bindings, $default]),
            StockStatus::InStock => $query->whereRaw("({$sql}) > {$threshold}", [...$bindings, $default]),
        };
    }

    /**
     * Items for variants that are currently offered to customers.
     *
     * @param  Builder<InventoryItem>  $query
     */
    /**
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeHasSellableStock(Builder $query): void
    {
        [$sql, $bindings] = self::sellableExpression();

        $query->whereRaw("({$sql}) > 0", $bindings);
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeForSellableCatalog(Builder $query): void
    {
        $query->whereHas('variant', fn (Builder $variants) => $variants
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $products) => $products->where('status', ProductStatus::Active)));
    }

    /**
     * SQL for the sellable quantity of the current inventory_items row.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private static function sellableExpression(): array
    {
        $batches = InventoryBatch::query()
            ->selectRaw('coalesce(sum(inventory_batches.quantity), 0)')
            ->whereColumn('inventory_batches.inventory_item_id', 'inventory_items.id')
            ->allocatable()
            ->toBase();

        return [
            'case when inventory_items.track_batches = 1 then ('.$batches->toSql().') else inventory_items.quantity_on_hand end',
            $batches->getBindings(),
        ];
    }
}
