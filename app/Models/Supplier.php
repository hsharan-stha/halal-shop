<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['shop_id', 'name', 'company_name', 'code', 'contact_name', 'email', 'phone', 'postal_code', 'prefecture', 'address', 'country_code', 'notes', 'is_active'])]
class Supplier extends Model
{
    use Auditable, BelongsToShop, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * @return HasMany<InventoryBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function hasOpenPurchaseOrders(): bool
    {
        return $this->purchaseOrders()->whereIn('status', [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived])->exists();
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
