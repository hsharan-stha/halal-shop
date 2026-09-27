<?php

namespace App\Models;

use App\Enums\Allergen;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\StorageType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'shop_id', 'category_id', 'brand_id', 'supplier_id', 'tax_class_id', 'sku', 'slug', 'name', 'japanese_name',
    'short_description', 'description', 'status', 'is_featured', 'published_at', 'halal_status', 'halal_notes',
    'ingredients', 'allergens', 'nutrition', 'storage_instructions', 'storage_type', 'country_of_origin',
    'manufacturer', 'importer', 'net_content', 'min_order_quantity', 'max_order_quantity', 'meta_title', 'meta_description',
])]
class Product extends Model
{
    use Auditable, HasFactory, HasTranslations, SoftDeletes;

    /**
     * Legally relevant label content. Changing any of these invalidates the
     * administrator's food label review.
     */
    public const FOOD_LABEL_FIELDS = [
        'ingredients', 'allergens', 'nutrition', 'storage_instructions', 'storage_type',
        'country_of_origin', 'manufacturer', 'importer', 'net_content',
    ];

    public const NUTRITION_FIELDS = ['basis', 'energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'salt_g'];

    protected function casts(): array
    {
        return [
            'short_description' => 'array',
            'description' => 'array',
            'halal_notes' => 'array',
            'ingredients' => 'array',
            'allergens' => AsEnumCollection::of(Allergen::class),
            'nutrition' => 'array',
            'storage_instructions' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'status' => ProductStatus::class,
            'halal_status' => HalalStatus::class,
            'storage_type' => StorageType::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'food_label_reviewed_at' => 'datetime',
            'min_order_quantity' => 'integer',
            'max_order_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->exists && $product->foodLabelChanged() && ! $product->isDirty('food_label_reviewed_at')) {
                $product->food_label_reviewed_at = null;
                $product->food_label_reviewed_by = null;
            }
        });

        static::forceDeleting(function (Product $product): void {
            $product->images()->get()->each->delete();
        });
    }

    /**
     * The halal shop that sells this product.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<HalalCertification, $this>
     */
    public function halalCertifications(): BelongsToMany
    {
        return $this->belongsToMany(HalalCertification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function foodLabelReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'food_label_reviewed_by')->withTrashed();
    }

    /**
     * Active, published (publication date reached) and sold by an open shop.
     *
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->where(fn (Builder $inner) => $inner->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->whereHas('shop', fn (Builder $shops) => $shops->where('is_active', true));
    }

    /**
     * Certified only when the admin chose "certified" AND at least one linked
     * certificate has been verified by staff and has not expired.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeCertifiedHalal(Builder $query): void
    {
        $query->where('halal_status', HalalStatus::Certified)
            ->whereHas('halalCertifications', fn (Builder $certificates) => $certificates->valid());
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Active && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isCertifiedHalal(): bool
    {
        if ($this->halal_status !== HalalStatus::Certified) {
            return false;
        }

        $certificates = $this->relationLoaded('halalCertifications')
            ? $this->halalCertifications
            : $this->halalCertifications()->get();

        return $certificates->contains(fn (HalalCertification $certificate) => $certificate->isValid());
    }

    /**
     * Status that may be shown to customers. A "certified" selection without
     * valid verified certificate data is downgraded to Unverified.
     */
    public function publicHalalStatus(): HalalStatus
    {
        if ($this->halal_status === HalalStatus::Certified && ! $this->isCertifiedHalal()) {
            return HalalStatus::Unverified;
        }

        return $this->halal_status;
    }

    /**
     * Whether label content changed in substance (empty values and JSON key
     * order are not treated as changes).
     */
    public function foodLabelChanged(): bool
    {
        $normalize = function (mixed $value): mixed {
            if (is_string($value) && json_validate($value)) {
                $value = json_decode($value, true);
            }

            return blank($value) ? null : $value;
        };

        foreach (self::FOOD_LABEL_FIELDS as $field) {
            if ($this->isDirty($field) && $normalize($this->getRawOriginal($field)) != $normalize($this->getAttributes()[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function hasReviewedFoodLabel(): bool
    {
        return $this->food_label_reviewed_at !== null;
    }

    /**
     * The gallery image shown on listing cards (lowest sort order).
     *
     * @return HasOne<ProductImage, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany([
            'sort_order' => 'min',
            'id' => 'min',
        ]);
    }

    public function primaryImage(): ?ProductImage
    {
        if ($this->relationLoaded('cover')) {
            return $this->cover;
        }

        return ($this->relationLoaded('images') ? $this->images : $this->images()->get())->first();
    }

    /**
     * Columns and relations needed to render a product card.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithStorefront(Builder $query): void
    {
        $variantPrice = fn (string $aggregate) => ProductVariant::query()
            ->selectRaw($aggregate.'(price)')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true);

        $query->select('products.*')
            ->withExists(['variants as in_stock' => fn (Builder $variants) => $variants
                ->where('is_active', true)
                ->whereHas('inventoryItem', fn (Builder $items) => $items->hasSellableStock())])
            ->addSelect([
                'min_price' => $variantPrice('min'),
                'max_price' => $variantPrice('max'),
            ])
            ->with([
                'brand:id,name,japanese_name,slug,deleted_at',
                'category:id,name,japanese_name,slug',
                'shop:id,name,slug,prefecture,city,latitude,longitude,is_active',
                'cover',
                'halalCertifications',
            ]);
    }

    /**
     * At least one active variant has stock that can be sold today.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->whereHas('variants', fn (Builder $variants) => $variants
            ->where('is_active', true)
            ->whereHas('inventoryItem', fn (Builder $items) => $items->hasSellableStock()));
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeOutOfStock(Builder $query): void
    {
        $query->whereDoesntHave('variants', fn (Builder $variants) => $variants
            ->where('is_active', true)
            ->whereHas('inventoryItem', fn (Builder $items) => $items->hasSellableStock()));
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function priceRange(): ?array
    {
        $prices = ($this->relationLoaded('variants') ? $this->variants : $this->variants()->get())
            ->where('is_active', true)
            ->pluck('price');

        return $prices->isEmpty() ? null : ['min' => (int) $prices->min(), 'max' => (int) $prices->max()];
    }
}
