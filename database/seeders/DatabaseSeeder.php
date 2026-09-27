<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Production-safe reference data is always seeded; fictional demo data only outside production.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            TaxSeeder::class,
        ]);

        if (app()->isProduction()) {
            return;
        }

        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            InventorySeeder::class,
        ]);
    }
}
