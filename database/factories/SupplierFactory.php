<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'SUP-'.fake()->unique()->numerify('###'),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '03-'.fake()->numerify('####-####'),
            'postal_code' => fake()->numerify('###-####'),
            'prefecture' => '東京都',
            'address' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }
}
