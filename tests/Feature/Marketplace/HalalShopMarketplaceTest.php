<?php

namespace Tests\Feature\Marketplace;

use App\Enums\RoleSlug;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalalShopMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_every_shop_and_a_shop_login_sees_only_its_own(): void
    {
        $osaka = Shop::factory()->create(['name' => '大阪ハラール']);
        $tokyo = Shop::factory()->create(['name' => '東京ハラール']);
        Product::factory()->create(['shop_id' => $osaka->id, 'name' => 'Osaka Rice']);
        Product::factory()->create(['shop_id' => $tokyo->id, 'name' => 'Tokyo Rice']);

        $shopUser = User::factory()->withRole(RoleSlug::HalalShop)->create();
        $shopUser->forceFill(['shop_id' => $osaka->id])->save();

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Osaka Rice')
            ->assertSee('Tokyo Rice');

        $this->actingAs($shopUser)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Osaka Rice')
            ->assertDontSee('Tokyo Rice');

        $this->actingAs($shopUser)
            ->get(route('admin.products.edit', Product::query()->where('name', 'Tokyo Rice')->first()))
            ->assertNotFound();

        $this->actingAs($shopUser)
            ->get(route('admin.shop-sales.index'))
            ->assertOk()
            ->assertSee('大阪ハラール')
            ->assertDontSee('東京ハラール');
    }

    public function test_a_platform_order_records_the_fulfilling_shop_and_commission(): void
    {
        $osaka = Shop::factory()->create([
            'name' => '大阪ハラール',
            'postal_code' => '530-0001',
            'prefecture' => '大阪府',
            'city' => '大阪市',
            'town' => '北区',
            'street' => '1-1',
            'latitude' => 34.705,
            'longitude' => 135.498,
        ]);
        $tokyo = Shop::factory()->at(35.681, 139.767)->create(['name' => '東京ハラール']);
        $product = Product::factory()->priced(1000)->create(['shop_id' => $tokyo->id, 'name' => 'Tokyo Chicken']);
        $variant = $product->variants()->where('is_default', true)->first();

        app(InventoryService::class)->receive($variant->inventoryItem, 4, [
            'expires_at' => now()->addMonths(3)->toDateString(),
        ]);

        $customer = $this->customer();
        $this->actingAs($customer)->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_to' => 'shop',
            'pickup_shop_id' => $osaka->id,
            'recipient_name' => '山田 花子',
            'phone' => '090-1234-5678',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        $order = Order::query()->first();
        $this->assertSame($tokyo->id, $order->shop_id);
        $this->assertSame($osaka->id, $order->pickup_shop_id);
        $this->assertSame(200, $order->commission_amount);
        $this->assertSame('530-0001', $order->postal_code);
        $this->assertSame('大阪府', $order->prefecture);

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->get(route('admin.shop-sales.index'))
            ->assertOk()
            ->assertSee('東京ハラール')
            ->assertSee('大阪ハラール');
    }

    public function test_the_storefront_lists_the_nearest_shop_first(): void
    {
        $osaka = Shop::factory()->at(34.6937, 135.5023)->create(['name' => '大阪ハラール', 'slug' => 'osaka-halal']);
        $tokyo = Shop::factory()->at(35.6812, 139.7671)->create(['name' => '東京ハラール', 'slug' => 'tokyo-halal']);
        Product::factory()->create(['shop_id' => $tokyo->id, 'name' => 'Tokyo Item', 'published_at' => now()]);
        Product::factory()->create(['shop_id' => $osaka->id, 'name' => 'Osaka Item', 'published_at' => now()->subDay()]);

        $this->withSession(['customer_latitude' => 34.70, 'customer_longitude' => 135.50])
            ->get(route('shop.index'))
            ->assertOk()
            ->assertSeeInOrder(['Osaka Item', 'Tokyo Item']);

        $this->get(route('halal-shops.index', ['q' => '大阪']))
            ->assertOk()
            ->assertSee('大阪ハラール')
            ->assertSee('height: 100dvh', false)
            ->assertDontSee('東京ハラール');
    }

    public function test_a_shop_cannot_be_registered_until_its_map_location_is_set(): void
    {
        $admin = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($admin)
            ->get(route('admin.halal-shops.create'))
            ->assertOk()
            ->assertSee(__('admin.halal_shops.map_hint'))
            ->assertSee(__('admin.halal_shops.place_search'))
            ->assertSee('height: 100dvh', false)
            ->assertDontSee('value="35.681236"', false);

        $payload = [
            'name' => '京都ハラール',
            'postal_code' => '600-8216',
            'prefecture' => '京都府',
            'city' => '京都市',
            'town' => '下京区',
            'street' => '1-1',
            'is_active' => '1',
            'user_name' => '京都 店長',
            'user_email' => 'kyoto-shop@example.com',
            'user_password' => 'Password1',
            'user_password_confirmation' => 'Password1',
        ];

        $this->actingAs($admin)
            ->post(route('admin.halal-shops.store'), $payload)
            ->assertSessionHasErrors(['latitude', 'longitude']);

        $this->assertDatabaseMissing('shops', ['name' => '京都ハラール']);

        $this->actingAs($admin)
            ->post(route('admin.halal-shops.store'), $payload + [
                'latitude' => 35.0036,
                'longitude' => 135.7681,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shops', [
            'name' => '京都ハラール',
            'latitude' => 35.0036,
            'longitude' => 135.7681,
        ]);
    }
}
