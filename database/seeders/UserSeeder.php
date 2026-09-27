<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Development accounts only. Never run in production (see DatabaseSeeder).
 * Logins use the factory password "password": superadmin@example.com,
 * customer@example.com, and shop@example.com for the main halal shop.
 */
class UserSeeder extends Seeder
{
    public const ShopEmail = 'shop@example.com';

    public function run(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::factory()->create(['email' => 'superadmin@example.com', 'name' => 'Super Admin', 'phone' => null]);
        $superAdmin->syncRoles([RoleSlug::SuperAdmin->value]);

        if (! User::query()->where('email', 'customer@example.com')->exists()) {
            User::factory()->customer()->create(['email' => 'customer@example.com', 'name' => '山田 花子']);
        }

        $existing = User::query()->customers()->count();

        if ($existing < 20) {
            User::factory()->count(20 - $existing)->customer()->create();
        }
    }

    /**
     * The login that manages the seeded main shop. Safe to call again.
     */
    public function ensureShopLogin(Shop $shop): User
    {
        $user = User::withTrashed()->where('email', self::ShopEmail)->first();

        if ($user === null) {
            $user = User::factory()->create([
                'name' => 'メイン店舗',
                'email' => self::ShopEmail,
                'phone' => null,
            ]);
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles([RoleSlug::HalalShop->value]);
        $user->forceFill([
            'shop_id' => $shop->id,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }
}
