<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['product_variant_id', 'disk', 'path', 'renditions', 'alt', 'width', 'height', 'sort_order'])]
class ProductImage extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return [
            'renditions' => 'array',
            'alt' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (ProductImage $image): void {
            Storage::disk($image->disk)->delete(array_values(array_filter([$image->path, ...array_values($image->renditions ?? [])])));
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isProcessed(): bool
    {
        return ! empty($this->renditions);
    }

    /**
     * URL of a rendition; falls back to the re-encoded upload until renditions exist.
     */
    public function url(string $size = 'medium'): string
    {
        return Storage::disk($this->disk)->url($this->renditions[$size] ?? $this->path);
    }

    public function srcset(): ?string
    {
        if (! $this->isProcessed()) {
            return null;
        }

        return collect(config('shop.image_sizes'))
            ->filter(fn (int $width, string $size) => isset($this->renditions[$size]))
            ->map(fn (int $width, string $size) => Storage::disk($this->disk)->url($this->renditions[$size]).' '.$width.'w')
            ->implode(', ');
    }

    public function altText(?Product $product = null): string
    {
        return $this->translate('alt') ?? ($product ?? $this->product)?->localizedName() ?? '';
    }
}
