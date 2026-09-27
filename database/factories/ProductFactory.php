<?php

namespace Database\Factories;

use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\StorageType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'shop_id' => Shop::factory(),
            'category_id' => Category::factory(),
            'tax_class_id' => fn () => TaxClass::query()->where('code', 'reduced')->value('id') ?? TaxClass::factory()->withRate(800),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'name' => $name,
            'status' => ProductStatus::Active,
            'published_at' => now()->subDay(),
            'halal_status' => HalalStatus::Unverified,
            'storage_type' => StorageType::Ambient,
            'min_order_quantity' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            if (! $product->variants()->exists()) {
                $variant = new ProductVariant(['sku' => $product->sku, 'price' => fake()->numberBetween(2, 60) * 50, 'is_active' => true]);
                $variant->is_default = true;
                $product->variants()->save($variant);
            }
        });
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft, 'published_at' => null]);
    }

    public function halal(HalalStatus $status): static
    {
        return $this->state(['halal_status' => $status]);
    }

    public function priced(int $yen): static
    {
        return $this->afterCreating(fn (Product $product) => $product->variants()->where('is_default', true)->update(['price' => $yen]));
    }
}
