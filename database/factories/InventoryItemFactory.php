<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Variants create their own stock record when saved, so this factory makes
 * the variant with model events disabled to avoid a duplicate record.
 * Prefer `$variant->inventoryItem` when a variant already exists.
 *
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variant_id' => fn () => ProductVariant::withoutEvents(fn () => ProductVariant::factory()->create())->id,
            'track_batches' => true,
            'quantity_on_hand' => 0,
            'low_stock_threshold' => null,
            'location' => null,
        ];
    }

    public function untracked(int $quantity = 0): static
    {
        return $this->state(['track_batches' => false, 'quantity_on_hand' => $quantity]);
    }
}
