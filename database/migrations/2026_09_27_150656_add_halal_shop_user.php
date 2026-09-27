<?php

use App\Models\Shop;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach a development login to the seeded main shop. Fresh installs create
     * that shop during seeding, after migrations, so this no-ops until the shop
     * exists and production never receives the known password.
     */
    public function up(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $shop = Shop::query()->where('slug', 'main-shop')->first();

        if ($shop === null) {
            return;
        }

        (new UserSeeder)->ensureShopLogin($shop);
    }

    public function down(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $userId = DB::table('users')->where('email', UserSeeder::ShopEmail)->value('id');

        if ($userId === null) {
            return;
        }

        DB::table('sessions')->where('user_id', $userId)->delete();

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();
        }

        DB::table('users')->where('id', $userId)->delete();
    }
};
