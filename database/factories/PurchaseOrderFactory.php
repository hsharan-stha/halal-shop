<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'order_number' => 'PO-'.now()->format('Ymd').'-'.fake()->unique()->numerify('9####'),
            'status' => PurchaseOrderStatus::Draft,
            'expected_at' => local_today()->addDays(7),
            'subtotal' => 0,
            'notes' => null,
        ];
    }

    public function ordered(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()]);
    }
}
