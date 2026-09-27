<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HalalShopUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_main_shop_gets_a_login_that_can_open_its_catalogue(): void
    {
        $shop = Shop::factory()->create(['slug' => 'main-shop', 'name' => 'メイン店舗']);
        $migration = require database_path('migrations/2026_09_27_150656_add_halal_shop_user.php');

        $migration->up();

        $user = User::query()->with('roles')->where('email', UserSeeder::ShopEmail)->first();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole(RoleSlug::HalalShop));
        $this->assertTrue($user->is_staff);
        $this->assertSame($shop->id, $user->shop_id);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->actingAs($user)->get(route('admin.products.index'))->assertOk();

        $migration->up();
        $this->assertSame(1, User::query()->where('email', UserSeeder::ShopEmail)->count());

        $migration->down();
        $this->assertNull(User::withTrashed()->where('email', UserSeeder::ShopEmail)->first());
    }

    public function test_seeding_the_catalogue_creates_the_shop_login_once(): void
    {
        $this->seed(TaxSeeder::class);
        $this->seed(CatalogSeeder::class);

        $user = User::query()->with(['roles', 'shop'])->where('email', UserSeeder::ShopEmail)->first();
        $products = Product::query()->count();

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole(RoleSlug::HalalShop));
        $this->assertSame('main-shop', $user->shop?->slug);

        $this->seed(CatalogSeeder::class);

        $this->assertSame(1, User::query()->where('email', UserSeeder::ShopEmail)->count());
        $this->assertSame($products, Product::query()->count());
    }
}
