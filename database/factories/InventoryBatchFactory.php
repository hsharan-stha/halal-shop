<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Enums\StorageType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Batches made here bypass InventoryService, so no movement is recorded;
 * the owning item's on-hand total is kept in step after creation.
 *
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(5, 60);

        return [
            'inventory_item_id' => InventoryItem::factory(),
            'batch_number' => 'B'.local_today()->format('ymd').'-'.Str::upper(Str::random(5)),
            'lot_number' => 'L'.fake()->numerify('####'),
            'initial_quantity' => $quantity,
            'quantity' => $quantity,
            'unit_cost' => fake()->numberBetween(1, 40) * 50,
            'manufactured_at' => local_today()->subDays(30),
            'expires_at' => local_today()->addDays(180),
            'received_at' => local_today(),
            'storage_type' => StorageType::Ambient,
            'status' => BatchStatus::Available,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (InventoryBatch $batch): void {
            if ($batch->status !== BatchStatus::Disposed) {
                InventoryItem::query()->whereKey($batch->inventory_item_id)->increment('quantity_on_hand', $batch->quantity);
            }
        });
    }

    public function expiresInDays(int $days): static
    {
        return $this->state(['expires_at' => local_today()->addDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => local_today()->subDays(2)]);
    }

    public function quarantined(): static
    {
        return $this->state(['status' => BatchStatus::Quarantined]);
    }

    public function quantity(int $quantity): static
    {
        return $this->state(['initial_quantity' => $quantity, 'quantity' => $quantity]);
    }
}
