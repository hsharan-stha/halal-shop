<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sku', 'barcode', 'name', 'price', 'compare_at_price', 'cost_price', 'weight_grams', 'is_active', 'sort_order'])]
class ProductVariant extends Model
{
    use Auditable, HasFactory, HasTranslations, SoftDeletes;

    protected static function booted(): void
    {
        static::created(fn (ProductVariant $variant) => $variant->inventoryItem()->firstOrCreate());
    }

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'weight_grams' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<InventoryItem, $this>
     */
    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    /**
     * @return HasMany<SupplierProduct, $this>
     */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * "Product · variant (SKU)" labels for admin select inputs.
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return static::query()
            ->with('product:id,name,japanese_name,deleted_at')
            ->whereHas('product')
            ->get(['id', 'product_id', 'sku', 'name'])
            ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => $variant->adminLabel()])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    public function adminLabel(): string
    {
        $name = $this->product?->localizedName() ?? '—';
        $variant = $this->translate('name');

        return $name.($variant ? ' · '.$variant : '').' ('.$this->sku.')';
    }

    public function label(): string
    {
        return $this->translate('name') ?? __('admin.variants.default_name');
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function discountPercent(): ?int
    {
        return $this->isOnSale() ? (int) floor(($this->compare_at_price - $this->price) * 100 / $this->compare_at_price) : null;
    }
}
