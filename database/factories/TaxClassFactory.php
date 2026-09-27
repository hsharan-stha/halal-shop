<?php

namespace Database\Factories;

use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxClass>
 */
class TaxClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'tax-'.fake()->unique()->lexify('????'),
            'name' => ['ja' => '標準税率', 'en' => 'Standard rate'],
            'is_default' => false,
        ];
    }

    public function withRate(int $basisPoints, string $from = '2019-10-01'): static
    {
        return $this->afterCreating(fn (TaxClass $class) => $class->rates()->create([
            'rate_bps' => $basisPoints,
            'effective_from' => $from,
        ]));
    }
}
