<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'name' => ['ja' => fake()->numberBetween(1, 5) * 100 .'g', 'en' => fake()->numberBetween(1, 5) * 100 .'g'],
            'price' => fake()->numberBetween(2, 60) * 50,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
