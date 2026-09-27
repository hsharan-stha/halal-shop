<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development accounts only. Never run in production (see DatabaseSeeder).
 * Credentials are documented in README (local development section).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['superadmin@example.com', 'Super Admin', RoleSlug::SuperAdmin],
            ['admin@example.com', 'Store Admin', RoleSlug::Admin],
            ['orders@example.com', 'Order Manager', RoleSlug::OrderManager],
            ['products@example.com', 'Product Manager', RoleSlug::ProductManager],
            ['inventory@example.com', 'Inventory Manager', RoleSlug::InventoryManager],
            ['content@example.com', 'Content Manager', RoleSlug::ContentManager],
            ['support@example.com', 'Support Agent', RoleSlug::SupportAgent],
        ];

        foreach ($staff as [$email, $name, $role]) {
            $user = User::query()->where('email', $email)->first()
                ?? User::factory()->create(['email' => $email, 'name' => $name, 'phone' => null]);
            $user->syncRoles([$role->value]);
        }

        if (! User::query()->where('email', 'customer@example.com')->exists()) {
            User::factory()->customer()->create(['email' => 'customer@example.com', 'name' => '山田 花子']);
        }

        $existing = User::query()->customers()->count();

        if ($existing < 20) {
            User::factory()->count(20 - $existing)->customer()->create();
        }
    }
}
