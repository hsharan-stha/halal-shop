<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'phone', 'email', 'postal_code', 'prefecture', 'city', 'town', 'street', 'building', 'latitude', 'longitude', 'is_active'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Platform orders fulfilled by this shop.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @param  Builder<Shop>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function summary(): string
    {
        return collect([
            $this->postal_code,
            $this->prefecture,
            $this->city,
            $this->town,
            $this->street,
            $this->building,
        ])->filter()->implode(' ');
    }

    /**
     * Great-circle distance in kilometres, or null when either point is missing.
     */
    public function distanceKm(?float $latitude, ?float $longitude): ?float
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $earth = 6371;
        $lat = deg2rad($this->latitude - $latitude);
        $lng = deg2rad($this->longitude - $longitude);
        $a = sin($lat / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($this->latitude)) * sin($lng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
