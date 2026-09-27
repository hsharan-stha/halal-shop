<?php

namespace Tests\Feature\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleSlug;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
    }

    public function test_cart_uses_the_server_price_and_refuses_unsellable_quantities(): void
    {
        $product = $this->stocked('Priced Lamb', 1000, 2);
        $variant = $this->variant($product);
        $draft = Product::factory()->draft()->create();

        $this->from(route('products.show', $product->slug))
            ->post(route('cart.store'), [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'price' => 1,
                'total' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Priced Lamb')
            ->assertSee('¥2,000')
            ->assertSee('data-cart-count="2"', false);

        $this->from(route('products.show', $product->slug))
            ->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 5])
            ->assertSessionHas('error');

        $this->from(route('shop.index'))
            ->post(route('cart.store'), ['variant_id' => $draft->variants()->first()->id, 'quantity' => 1])
            ->assertSessionHas('error');

        $this->get(route('cart.index'))->assertOk()->assertSee('¥2,000');
    }

    public function test_guest_cart_merges_into_the_account_on_login(): void
    {
        $product = $this->stocked('Merge Lamb', 800, 3);
        $variant = $this->variant($product);
        $customer = $this->customer();

        $this->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();

        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($customer);
        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_checkout_creates_an_unpaid_order_and_allocates_stock(): void
    {
        $product = $this->stocked('Checkout Lamb', 1000, 5);
        $variant = $this->variant($product);
        $customer = $this->customer(['name' => '山田 花子']);

        $this->actingAs($customer)
            ->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                ...$this->address(),
                'payment_method' => 'bank_transfer',
                'total' => 1,
                'payment_status' => 'paid',
            ])
            ->assertRedirect();

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertEquals($customer->id, $order->user_id);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(2000, $order->items_total);
        $this->assertSame(660, $order->shipping_total);
        $this->assertSame(0, $order->cod_fee);
        $this->assertSame(2660, $order->total);
        $this->assertSame('100-0001', $order->postal_code);
        $this->assertSame(3, $variant->inventoryItem()->first()->quantity_on_hand);
        $this->assertDatabaseCount('cart_items', 0);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__('enums.payment_status.unpaid'));

        $this->actingAs($this->customer())
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_cash_on_delivery_adds_the_fee_and_stays_unpaid_until_delivery(): void
    {
        $product = $this->stocked('Cod Lamb', 1000, 2);
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('cart.store'), [
            'variant_id' => $this->variant($product)->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)->post(route('checkout.store'), [
            ...$this->address(),
            'payment_method' => 'cash_on_delivery',
        ])->assertRedirect();

        $order = Order::query()->first();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(330, $order->cod_fee);
        $this->assertSame(1990, $order->total);
    }

    public function test_super_admin_confirms_payment_and_shipment_and_a_customer_cannot(): void
    {
        $order = $this->placedOrder('Admin Visible Lamb');
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($this->customer())
            ->get(route('admin.orders.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('山田 花子');

        $this->actingAs($manager)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Admin Visible Lamb');

        $order->load('customer');

        $this->actingAs($order->customer)
            ->post(route('admin.orders.pay', $order))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('admin.orders.ship', $order))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($manager)->post(route('admin.orders.pay', $order))->assertRedirect()->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);

        $this->actingAs($manager)->post(route('admin.orders.ship', $order))->assertSessionHas('success');
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_cancelling_an_unshipped_order_returns_the_stock(): void
    {
        $product = $this->stocked('Cancel Lamb', 900, 4);
        $variant = $this->variant($product);
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            ...$this->address(),
            'payment_method' => 'bank_transfer',
        ]);

        $order = Order::query()->first();
        $this->assertSame(2, $variant->inventoryItem()->first()->quantity_on_hand);

        $this->actingAs($customer)->post(route('account.orders.cancel', $order))->assertRedirect()->assertSessionHas('success');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(4, $variant->inventoryItem()->first()->quantity_on_hand);
    }

    public function test_guests_are_sent_to_login_before_checkout(): void
    {
        $product = $this->stocked('Guest Lamb', 500, 1);

        $this->post(route('cart.store'), ['variant_id' => $this->variant($product)->id, 'quantity' => 1]);

        $this->get(route('checkout.create'))->assertRedirect(route('login'));
    }

    private function stocked(string $name, int $price, int $quantity): Product
    {
        $product = Product::factory()->priced($price)->create(['name' => $name]);
        $item = $this->variant($product)->inventoryItem;

        app(InventoryService::class)->receive($item, $quantity, [
            'expires_at' => now()->addMonths(6)->toDateString(),
        ]);

        return $product;
    }

    private function variant(Product $product): ProductVariant
    {
        return $product->variants()->where('is_default', true)->first();
    }

    private function placedOrder(string $name): Order
    {
        $product = $this->stocked($name, 1000, 3);
        $customer = $this->customer(['name' => '山田 花子']);

        $this->actingAs($customer)->post(route('cart.store'), [
            'variant_id' => $this->variant($product)->id,
            'quantity' => 1,
        ]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            ...$this->address(),
            'payment_method' => 'bank_transfer',
        ]);

        return Order::query()->first();
    }

    /**
     * @return array<string, string>
     */
    private function address(): array
    {
        return [
            'recipient_name' => '山田 花子',
            'phone' => '090-1234-5678',
            'postal_code' => '100-0001',
            'prefecture' => '東京都',
            'city' => '千代田区',
            'town' => '千代田',
            'street' => '1-1',
        ];
    }
}
