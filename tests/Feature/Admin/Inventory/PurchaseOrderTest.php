<?php

namespace Tests\Feature\Admin\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleSlug;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseOrderService;
use Carbon\CarbonImmutable;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Tokyo'));
        $this->manager = $this->staff(RoleSlug::SuperAdmin);
    }

    public function test_draft_totals_are_calculated_on_the_server(): void
    {
        $supplier = Supplier::factory()->create();
        [$first, $second] = ProductVariant::factory()->count(2)->create(['product_id' => Product::factory()->create()->id]);

        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'expected_at' => '2026-06-25',
                'subtotal' => 1,
                'lines' => [
                    ['product_variant_id' => $first->id, 'quantity' => 10, 'unit_cost' => 300, 'line_total' => 1],
                    ['product_variant_id' => '', 'quantity' => '', 'unit_cost' => ''],
                    ['product_variant_id' => $second->id, 'quantity' => 4, 'unit_cost' => 1250],
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->sole();
        $this->assertSame(8000, $order->subtotal);
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame('PO-20260615-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), $order->order_number);
        $this->assertSame($this->manager->id, $order->created_by);
        $this->assertSame(2, $order->items()->count());
    }

    public function test_orders_need_an_active_supplier_and_distinct_lines(): void
    {
        $inactive = Supplier::factory()->create(['is_active' => false]);
        $variant = ProductVariant::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.store'), [
                'supplier_id' => $inactive->id,
                'lines' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_cost' => 100],
                    ['product_variant_id' => $variant->id, 'quantity' => 2, 'unit_cost' => 100],
                ],
            ])
            ->assertSessionHasErrors(['supplier_id', 'lines.0.product_variant_id', 'lines.1.product_variant_id']);
        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.store'), ['supplier_id' => Supplier::factory()->create()->id, 'lines' => [['product_variant_id' => '']]])
            ->assertSessionHasErrors('lines');

        $this->assertFalse(PurchaseOrder::query()->exists());
    }

    public function test_ordered_purchase_orders_can_no_longer_be_edited(): void
    {
        $order = $this->order([10]);

        $this->actingAs($this->manager)->post(route('admin.purchase-orders.order', $order))->assertSessionHas('success');
        $this->actingAs($this->manager)
            ->get(route('admin.purchase-orders.edit', $order))
            ->assertRedirect(route('admin.purchase-orders.show', $order));
        $this->actingAs($this->manager)
            ->put(route('admin.purchase-orders.update', $order), [
                'supplier_id' => $order->supplier_id,
                'lines' => [['product_variant_id' => $order->items->first()->product_variant_id, 'quantity' => 999, 'unit_cost' => 1]],
            ])
            ->assertSessionHas('error', __('admin.purchase_orders.not_editable'));

        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);
        $this->assertSame(10, $order->items()->first()->quantity);
    }

    public function test_partial_then_full_receipt_books_batches_against_the_order(): void
    {
        $order = $this->ordered([10, 5]);
        [$first, $second] = $order->items;

        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [
                $first->id => ['quantity' => 6, 'expires_at' => '2026-12-01', 'lot_number' => 'LOT-1'],
                $second->id => ['quantity' => 0],
            ]])
            ->assertRedirect(route('admin.purchase-orders.show', $order))
            ->assertSessionHas('success', __('admin.purchase_orders.received', ['count' => 6]));

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);
        $batch = InventoryBatch::query()->sole();
        $this->assertSame([$order->supplier_id, $first->id, $first->unit_cost, 6], [$batch->supplier_id, $batch->purchase_order_item_id, $batch->unit_cost, $batch->quantity]);
        $movement = InventoryMovement::query()->sole();
        $this->assertSame(InventoryMovementType::Purchase, $movement->type);
        $this->assertTrue($movement->reference->is($order));

        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [
                $first->id => ['quantity' => 4, 'expires_at' => '2026-12-15'],
                $second->id => ['quantity' => 5, 'expires_at' => '2027-01-10'],
            ]])
            ->assertSessionHas('success');

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->received_at);
        $this->assertSame(15, InventoryBatch::query()->sum('quantity'));
    }

    public function test_receipts_are_validated_against_the_outstanding_quantity(): void
    {
        $order = $this->ordered([10]);
        $line = $order->items->first();
        $other = $this->ordered([3])->items->first();

        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [$line->id => ['quantity' => 11, 'expires_at' => '2026-12-01']]])
            ->assertSessionHasErrors(["lines.{$line->id}.quantity" => __('admin.purchase_orders.over_receipt', ['remaining' => 10])]);
        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [$line->id => ['quantity' => 2]]])
            ->assertSessionHasErrors(["lines.{$line->id}.expires_at" => __('admin.purchase_orders.expiry_required')]);
        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [$other->id => ['quantity' => 1, 'expires_at' => '2026-12-01']]])
            ->assertSessionHasErrors(['lines' => __('admin.purchase_orders.invalid_line')]);
        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [$line->id => ['quantity' => 0]]])
            ->assertSessionHasErrors(['lines' => __('admin.purchase_orders.nothing_to_receive')]);

        $this->assertFalse(InventoryBatch::query()->exists());
        $this->assertSame(0, $line->fresh()->quantity_received);
    }

    public function test_drafts_cannot_be_received(): void
    {
        $order = $this->order([4]);

        $this->actingAs($this->manager)
            ->get(route('admin.purchase-orders.receive', $order))
            ->assertRedirect(route('admin.purchase-orders.show', $order));
        $this->actingAs($this->manager)
            ->post(route('admin.purchase-orders.receive.store', $order), ['lines' => [$order->items->first()->id => ['quantity' => 4, 'expires_at' => '2026-12-01']]])
            ->assertSessionHas('error', __('admin.purchase_orders.not_receivable'));

        $this->assertFalse(InventoryBatch::query()->exists());
    }

    public function test_only_orders_without_deliveries_can_be_cancelled(): void
    {
        $open = $this->ordered([5]);
        $partial = $this->ordered([5]);
        app(PurchaseOrderService::class)->receive($partial, [$partial->items->first()->id => ['quantity' => 2, 'expires_at' => '2026-12-01']], $this->manager);

        $this->actingAs($this->manager)->post(route('admin.purchase-orders.cancel', $open), ['reason' => 'Supplier out of stock'])->assertSessionHas('success');
        $this->actingAs($this->manager)->post(route('admin.purchase-orders.cancel', $partial))->assertSessionHas('error', __('admin.purchase_orders.invalid_state'));

        $this->assertSame(PurchaseOrderStatus::Cancelled, $open->fresh()->status);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $partial->fresh()->status);
    }

    public function test_purchase_order_pages_render(): void
    {
        $draft = $this->order([3]);
        $ordered = $this->ordered([6, 2]);

        $this->actingAs($this->manager)->get(route('admin.purchase-orders.index'))->assertOk()->assertSee($draft->order_number)->assertSee($ordered->order_number);
        $this->actingAs($this->manager)->get(route('admin.purchase-orders.create', ['supplier' => $draft->supplier_id]))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.purchase-orders.edit', $draft))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.purchase-orders.show', $ordered))->assertOk()->assertSee(__('admin.purchase_orders.receive'));
        $this->actingAs($this->manager)->get(route('admin.purchase-orders.receive', $ordered))->assertOk()->assertSee('lines['.$ordered->items->first()->id.'][expires_at]', false);
    }

    public function test_a_customer_cannot_use_purchasing(): void
    {
        $order = $this->ordered([3]);

        $customer = $this->customer();
        $this->actingAs($customer)->get(route('admin.purchase-orders.index'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.purchase-orders.cancel', $order))->assertForbidden();

        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);
    }

    /**
     * @param  list<int>  $quantities
     */
    private function order(array $quantities): PurchaseOrder
    {
        $product = Product::factory()->create();
        $lines = array_map(fn (int $quantity) => [
            'product_variant_id' => ProductVariant::factory()->create(['product_id' => $product->id])->id,
            'quantity' => $quantity,
            'unit_cost' => 200,
        ], $quantities);

        return app(PurchaseOrderService::class)
            ->save(
                new PurchaseOrder(['shop_id' => $product->shop_id]),
                ['supplier_id' => Supplier::factory()->create()->id, 'expected_at' => '2026-06-25', 'notes' => null],
                $lines,
                $this->manager,
            )
            ->load('items');
    }

    /**
     * @param  list<int>  $quantities
     */
    private function ordered(array $quantities): PurchaseOrder
    {
        $order = $this->order($quantities);
        app(PurchaseOrderService::class)->markOrdered($order, $this->manager);

        return $order->fresh('items');
    }
}
