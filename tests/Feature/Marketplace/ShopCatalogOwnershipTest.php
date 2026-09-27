<?php

namespace Tests\Feature\Marketplace;

use App\Enums\PaymentStatus;
use App\Enums\RoleSlug;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseOrderService;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCatalogOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $shopUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->shop = Shop::factory()->create(['name' => '大阪ハラール']);
        $this->shopUser = $this->staff(RoleSlug::HalalShop);
        $this->shopUser->forceFill(['shop_id' => $this->shop->id])->save();
    }

    public function test_a_shop_owns_the_catalogue_rows_it_creates(): void
    {
        $this->actingAs($this->shopUser)
            ->post(route('admin.categories.store'), ['name' => 'Shop Only Category', 'is_active' => '1'])
            ->assertRedirect();

        $this->actingAs($this->shopUser)
            ->post(route('admin.brands.store'), ['name' => 'Shop Only Brand', 'is_active' => '1'])
            ->assertRedirect();

        $this->actingAs($this->shopUser)
            ->post(route('admin.suppliers.store'), ['name' => 'Shop Only Supplier', 'country_code' => 'JP', 'is_active' => '1'])
            ->assertRedirect();

        $this->assertSame($this->shop->id, Category::query()->sole()->shop_id);
        $this->assertSame($this->shop->id, Brand::query()->sole()->shop_id);
        $this->assertSame($this->shop->id, Supplier::query()->sole()->shop_id);
    }

    public function test_a_shop_may_use_but_not_change_shared_rows(): void
    {
        $shared = Category::factory()->create(['name' => 'Shared Category']);
        $sharedBrand = Brand::factory()->create(['name' => 'Shared Brand']);
        $sharedSupplier = Supplier::factory()->create(['name' => 'Shared Supplier']);

        $this->actingAs($this->shopUser)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Shared Category')
            ->assertSee(__('admin.owner_platform'));

        $this->actingAs($this->shopUser)->get(route('admin.categories.edit', $shared))->assertForbidden();
        $this->actingAs($this->shopUser)->delete(route('admin.categories.destroy', $shared))->assertForbidden();
        $this->actingAs($this->shopUser)->get(route('admin.brands.edit', $sharedBrand))->assertForbidden();
        $this->actingAs($this->shopUser)->get(route('admin.suppliers.edit', $sharedSupplier))->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $shared->id]);
    }

    public function test_a_shop_cannot_reach_another_shops_catalogue_or_purchasing(): void
    {
        $otherShop = Shop::factory()->create(['name' => '東京ハラール']);
        $otherCategory = Category::factory()->create(['name' => 'Tokyo Category', 'shop_id' => $otherShop->id]);
        $otherSupplier = Supplier::factory()->create(['name' => 'Tokyo Supplier', 'shop_id' => $otherShop->id]);
        $otherOrder = PurchaseOrder::factory()->create(['shop_id' => $otherShop->id, 'order_number' => 'PO-TOKYO-1']);

        $this->actingAs($this->shopUser)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertDontSee('Tokyo Category');

        $this->actingAs($this->shopUser)
            ->get(route('admin.suppliers.index'))
            ->assertOk()
            ->assertDontSee('Tokyo Supplier');

        $this->actingAs($this->shopUser)
            ->get(route('admin.purchase-orders.index'))
            ->assertOk()
            ->assertDontSee('PO-TOKYO-1');

        $this->actingAs($this->shopUser)->get(route('admin.categories.edit', $otherCategory))->assertNotFound();
        $this->actingAs($this->shopUser)->get(route('admin.purchase-orders.show', $otherOrder))->assertNotFound();
    }

    public function test_a_purchase_order_belongs_to_the_shop_that_ordered_the_goods(): void
    {
        $supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);
        $ownVariant = ProductVariant::factory()->create([
            'product_id' => Product::factory()->create(['shop_id' => $this->shop->id])->id,
        ]);
        $otherVariant = ProductVariant::factory()->create([
            'product_id' => Product::factory()->create(['shop_id' => Shop::factory()->create()->id])->id,
        ]);

        $this->actingAs($this->shopUser)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'lines' => [['product_variant_id' => $ownVariant->id, 'quantity' => 4, 'unit_cost' => 500]],
            ])
            ->assertRedirect();

        $this->assertSame($this->shop->id, PurchaseOrder::query()->sole()->shop_id);

        $this->actingAs($this->shopUser)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'lines' => [['product_variant_id' => $otherVariant->id, 'quantity' => 1, 'unit_cost' => 500]],
            ])
            ->assertSessionHasErrors(['lines' => __('admin.purchase_orders.single_shop')]);

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'lines' => [
                    ['product_variant_id' => $ownVariant->id, 'quantity' => 1, 'unit_cost' => 500],
                    ['product_variant_id' => $otherVariant->id, 'quantity' => 1, 'unit_cost' => 500],
                ],
            ])
            ->assertSessionHasErrors(['lines' => __('admin.purchase_orders.single_shop')]);

        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_a_shop_reads_its_orders_while_the_super_admin_handles_them(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->shopUser)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($this->shopUser)->post(route('admin.orders.pay', $order))->assertForbidden();
        $this->actingAs($this->shopUser)->post(route('admin.orders.ship', $order))->assertForbidden();
        $this->actingAs($this->shopUser)->post(route('admin.orders.cancel', $order))->assertForbidden();

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.orders.pay', $order))
            ->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_receiving_a_shop_purchase_order_only_adds_stock_to_that_shop(): void
    {
        $variant = ProductVariant::factory()->create([
            'product_id' => Product::factory()->create(['shop_id' => $this->shop->id])->id,
        ]);

        $order = app(PurchaseOrderService::class)->save(
            new PurchaseOrder(['shop_id' => $this->shop->id]),
            ['supplier_id' => Supplier::factory()->create(['shop_id' => $this->shop->id])->id, 'expected_at' => null, 'notes' => null],
            [['product_variant_id' => $variant->id, 'quantity' => 6, 'unit_cost' => 400]],
            $this->shopUser,
        );

        app(PurchaseOrderService::class)->markOrdered($order, $this->shopUser);

        $this->actingAs($this->shopUser)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [
                $order->items()->sole()->id => ['quantity' => 6, 'expires_at' => now()->addMonths(6)->toDateString()],
            ]])
            ->assertSessionHas('success');

        $this->assertSame(6, $variant->inventoryItem->fresh()->quantity_on_hand);
    }

    /**
     * An unpaid bank-transfer order fulfilled by this shop.
     */
    private function placedOrder(): Order
    {
        $product = Product::factory()->priced(1000)->create(['shop_id' => $this->shop->id]);
        $variant = $product->variants()->where('is_default', true)->first();

        app(InventoryService::class)->receive($variant->inventoryItem, 2, [
            'expires_at' => now()->addMonths(6)->toDateString(),
        ]);

        $customer = $this->customer(['name' => '山田 花子']);
        $this->actingAs($customer)->post(route('cart.store'), ['variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->actingAs($customer)->post(route('checkout.store'), [
            'recipient_name' => '山田 花子',
            'phone' => '090-1234-5678',
            'postal_code' => '100-0001',
            'prefecture' => '東京都',
            'city' => '千代田区',
            'town' => '千代田',
            'street' => '1-1',
            'payment_method' => 'bank_transfer',
        ])->assertRedirect();

        return Order::query()->sole();
    }
}
