<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * Japanese consumption tax classes. Reference data required in every
 * environment; rates are effective-dated so historic orders stay correct.
 */
class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            'standard' => [
                'name' => ['ja' => '標準税率', 'en' => 'Standard rate'],
                'is_default' => false,
                'rates' => [['rate_bps' => 800, 'effective_from' => '2014-04-01', 'effective_to' => '2019-09-30'], ['rate_bps' => 1000, 'effective_from' => '2019-10-01', 'effective_to' => null]],
            ],
            'reduced' => [
                'name' => ['ja' => '軽減税率（飲食料品）', 'en' => 'Reduced rate (food & beverages)'],
                'is_default' => true,
                'rates' => [['rate_bps' => 800, 'effective_from' => '2014-04-01', 'effective_to' => null]],
            ],
        ];

        foreach ($classes as $code => $definition) {
            $class = TaxClass::query()->firstOrCreate(['code' => $code], ['name' => $definition['name'], 'is_default' => $definition['is_default']]);

            if ($class->rates()->exists()) {
                continue;
            }

            foreach ($definition['rates'] as $rate) {
                $class->rates()->create($rate);
            }
        }
    }
}
