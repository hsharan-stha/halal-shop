<?php

namespace Tests\Feature\Admin\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleSlug;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->manager = $this->staff(RoleSlug::InventoryManager);
    }

    public function test_domestic_supplier_contact_details_are_normalised(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.suppliers.store'), [
                'name' => 'Demo Foods',
                'company_name' => 'Demo Foods K.K.',
                'code' => ' demo-01 ',
                'phone' => '０３１２３４５６７８',
                'postal_code' => '１０００００１',
                'country_code' => 'jp',
                'prefecture' => '東京都',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $supplier = Supplier::query()->sole();
        $this->assertSame(['DEMO-01', '0312345678', '100-0001', 'JP', true], [$supplier->code, $supplier->phone, $supplier->postal_code, $supplier->country_code, $supplier->is_active]);
    }

    public function test_domestic_address_rules_apply_only_to_japanese_suppliers(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.suppliers.store'), ['name' => 'Demo Foods', 'country_code' => 'JP', 'postal_code' => '12345', 'prefecture' => 'Selangor'])
            ->assertSessionHasErrors(['postal_code', 'prefecture']);

        $this->actingAs($this->manager)
            ->post(route('admin.suppliers.store'), ['name' => 'Demo Export Sdn Bhd', 'country_code' => 'MY', 'postal_code' => '50450', 'prefecture' => 'Selangor', 'phone' => '+60 3-1234 5678'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Selangor', Supplier::query()->sole()->prefecture);
    }

    public function test_supplier_codes_are_unique(): void
    {
        Supplier::factory()->create(['code' => 'SUP-001']);

        $this->actingAs($this->manager)
            ->post(route('admin.suppliers.store'), ['name' => 'Another', 'code' => 'sup-001', 'country_code' => 'JP'])
            ->assertSessionHasErrors('code');
    }

    public function test_suppliers_with_open_purchase_orders_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        $order = PurchaseOrder::factory()->for($supplier)->ordered()->create();

        $this->actingAs($this->manager)
            ->delete(route('admin.suppliers.destroy', $supplier))
            ->assertSessionHas('error', __('admin.suppliers.has_open_orders'));
        $this->assertNotSoftDeleted($supplier);

        $order->forceFill(['status' => PurchaseOrderStatus::Received])->save();
        $this->actingAs($this->manager)
            ->delete(route('admin.suppliers.destroy', $supplier))
            ->assertRedirect(route('admin.suppliers.index'));
        $this->assertSoftDeleted($supplier);
    }

    public function test_marking_a_supplier_preferred_clears_other_preferred_suppliers(): void
    {
        $variant = ProductVariant::factory()->create();
        $current = Supplier::factory()->create()->supplierProducts()->create(['product_variant_id' => $variant->id, 'is_preferred' => true]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.suppliers.products.store', $supplier), ['product_variant_id' => $variant->id, 'unit_cost' => 480, 'lead_time_days' => 3, 'is_preferred' => '1'])
            ->assertSessionHas('success');

        $this->assertFalse($current->fresh()->is_preferred);
        $this->assertTrue($supplier->supplierProducts()->sole()->is_preferred);
        $this->assertSame(480, $supplier->supplierProducts()->sole()->unit_cost);
    }

    public function test_supplier_products_can_only_be_removed_through_their_own_supplier(): void
    {
        $owner = Supplier::factory()->create();
        $link = $owner->supplierProducts()->create(['product_variant_id' => ProductVariant::factory()->create()->id]);

        $this->actingAs($this->manager)
            ->delete(route('admin.suppliers.products.destroy', [Supplier::factory()->create(), $link]))
            ->assertNotFound();
        $this->assertModelExists($link);

        $this->actingAs($this->manager)
            ->delete(route('admin.suppliers.products.destroy', [$owner, $link]))
            ->assertSessionHas('success');
        $this->assertModelMissing($link);
    }

    public function test_supplier_pages_render(): void
    {
        $supplier = Supplier::factory()->create(['company_name' => 'Demo Wholesale K.K.']);
        $supplier->supplierProducts()->create(['product_variant_id' => ProductVariant::factory()->create()->id, 'unit_cost' => 300, 'is_preferred' => true]);
        PurchaseOrder::factory()->for($supplier)->create();

        $this->actingAs($this->manager)->get(route('admin.suppliers.index'))->assertOk()->assertSee($supplier->name);
        $this->actingAs($this->manager)->get(route('admin.suppliers.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.suppliers.edit', $supplier))->assertOk();
        $this->actingAs($this->manager)->get(route('admin.suppliers.show', $supplier))->assertOk()->assertSee('Demo Wholesale K.K.')->assertSee(__('admin.suppliers.preferred'));
    }

    public function test_view_only_staff_cannot_change_suppliers(): void
    {
        $viewer = $this->staff(RoleSlug::ProductManager);

        $this->actingAs($viewer)->get(route('admin.suppliers.index'))->assertOk()->assertDontSee(route('admin.suppliers.create'));
        $this->actingAs($viewer)->post(route('admin.suppliers.store'), ['name' => 'Sneaky', 'country_code' => 'JP'])->assertForbidden();

        $this->assertFalse(Supplier::query()->exists());
    }
}
