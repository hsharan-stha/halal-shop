<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\HasTranslations;
use App\Services\Media\ImageStorage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['shop_id', 'name', 'japanese_name', 'slug', 'description', 'country_of_origin', 'website_url', 'sort_order', 'is_active'])]
class Brand extends Model
{
    use Auditable, BelongsToShop, HasFactory, HasTranslations, SoftDeletes;

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<HalalCertification, $this>
     */
    public function halalCertifications(): HasMany
    {
        return $this->hasMany(HalalCertification::class);
    }

    /**
     * @param  Builder<Brand>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function logoUrl(): ?string
    {
        return app(ImageStorage::class)->url($this->logo_path);
    }
}
