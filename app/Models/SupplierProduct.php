<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_variant_id', 'supplier_sku', 'unit_cost', 'lead_time_days', 'min_order_quantity', 'is_preferred'])]
class SupplierProduct extends Model
{
    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'lead_time_days' => 'integer',
            'min_order_quantity' => 'integer',
            'is_preferred' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
