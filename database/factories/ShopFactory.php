<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'phone' => '0312345678',
            'prefecture' => '東京都',
            'city' => '渋谷区',
            'town' => '神宮前',
            'street' => '1-2-3',
            'latitude' => fake()->latitude(35.4, 35.8),
            'longitude' => fake()->longitude(139.4, 139.9),
            'is_active' => true,
        ];
    }

    public function at(float $latitude, float $longitude): static
    {
        return $this->state(fn () => [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }
}
