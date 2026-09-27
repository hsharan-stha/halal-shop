<?php

namespace Tests\Feature\Admin\Inventory;

use App\Enums\BatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\RoleSlug;
use App\Enums\StockStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Tokyo'));
    }

    public function test_inventory_pages_render_with_stock_in_every_state(): void
    {
        $item = $this->item();
        $batch = $this->receive($item, 8, '2026-06-20');
        $this->receive($item, 4, '2026-12-01', ['status' => BatchStatus::Quarantined]);
        $untracked = InventoryItem::factory()->untracked(3)->create();
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee($item->variant->sku)
            ->assertSee($untracked->variant->sku);
        $this->actingAs($manager)->get(route('admin.inventory.index', ['stock' => 'out_of_stock']))
            ->assertOk()
            ->assertDontSee($item->variant->sku);
        $this->actingAs($manager)->get(route('admin.inventory.show', $item))
            ->assertOk()
            ->assertSee($batch->batch_number)
            ->assertSee(__('enums.batch_status.quarantined'));
        $this->actingAs($manager)->get(route('admin.inventory.show', $untracked))
            ->assertOk()
            ->assertSee(__('admin.inventory.apply_adjustment'));
        $this->actingAs($manager)->get(route('admin.inventory.receive', $item))->assertOk();
        $this->actingAs($manager)->get(route('admin.batches.index', ['expiry' => 'expiring']))
            ->assertOk()
            ->assertSee($batch->batch_number);
        $this->actingAs($manager)->get(route('admin.batches.show', $batch))
            ->assertOk()
            ->assertSee(__('admin.batches.dispose'));
    }

    public function test_staff_without_inventory_access_are_refused(): void
    {
        $item = $this->item();

        $this->actingAs($this->customer())
            ->get(route('admin.inventory.index'))
            ->assertForbidden();
        $this->actingAs($this->customer())
            ->post(route('admin.inventory.receive.store', $item), ['quantity' => 5, 'expires_at' => '2026-09-01'])
            ->assertForbidden();

        $this->assertSame(0, $item->fresh()->quantity_on_hand);
    }

    public function test_a_customer_cannot_view_stock(): void
    {
        $item = $this->item();
        $batch = $this->receive($item, 5, '2026-09-01');

        $this->actingAs($this->customer())->get(route('admin.inventory.show', $item))->assertForbidden();
        $this->actingAs($this->customer())->get(route('admin.batches.show', $batch))->assertForbidden();
        $this->actingAs($this->customer())->post(route('admin.batches.dispose', $batch), ['quantity' => 1, 'type' => 'damage', 'reason' => 'x'])
            ->assertForbidden();
    }

    public function test_receiving_stock_creates_a_batch_and_a_movement(): void
    {
        $item = $this->item();
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)
            ->post(route('admin.inventory.receive.store', $item), [
                'quantity' => 24,
                'expires_at' => '2026-12-31',
                'lot_number' => 'LOT-77',
                'location' => 'Freezer 1',
                'unit_cost' => 450,
                'quarantine' => '1',
            ])
            ->assertRedirect(route('admin.inventory.show', $item))
            ->assertSessionHas('success', __('admin.inventory.received', ['quantity' => 24]));

        $batch = InventoryBatch::query()->sole();
        $this->assertSame(['LOT-77', 'Freezer 1', 450, 24, BatchStatus::Quarantined], [$batch->lot_number, $batch->location, $batch->unit_cost, $batch->quantity, $batch->status]);
        $this->assertSame('2026-12-31', $batch->expires_at->toDateString());
        $this->assertSame(24, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, $item->fresh()->sellableQuantity());
        $this->assertSame($manager->id, InventoryMovement::query()->sole()->user_id);
    }

    public function test_receiving_tracked_stock_requires_a_future_expiry_date(): void
    {
        $item = $this->item();
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)
            ->post(route('admin.inventory.receive.store', $item), ['quantity' => 5])
            ->assertSessionHasErrors('expires_at');
        $this->actingAs($manager)
            ->post(route('admin.inventory.receive.store', $item), ['quantity' => 5, 'expires_at' => '2026-06-14'])
            ->assertSessionHasErrors('expires_at');
        $this->actingAs($manager)
            ->post(route('admin.inventory.receive.store', $item), ['quantity' => 0, 'expires_at' => '2026-09-01'])
            ->assertSessionHasErrors('quantity');

        $this->assertFalse(InventoryBatch::query()->exists());
    }

    public function test_quarantine_requires_a_reason_and_blocks_sales(): void
    {
        $item = $this->item();
        $batch = $this->receive($item, 5, '2026-09-01');
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)
            ->post(route('admin.batches.status', $batch), ['status' => 'quarantined'])
            ->assertSessionHasErrors('reason');
        $this->actingAs($manager)
            ->post(route('admin.batches.status', $batch), ['status' => 'quarantined', 'reason' => 'Packaging damaged'])
            ->assertSessionHas('success');

        $this->assertSame(BatchStatus::Quarantined, $batch->fresh()->status);
        $this->assertSame('Packaging damaged', $batch->fresh()->status_reason);
        $this->assertSame(0, $item->fresh()->sellableQuantity());
    }

    public function test_disposal_cannot_exceed_the_batch_quantity(): void
    {
        $item = $this->item();
        $batch = $this->receive($item, 5, '2026-09-01');
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)
            ->post(route('admin.batches.dispose', $batch), ['quantity' => 6, 'type' => 'damage', 'reason' => 'Dropped'])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($manager)
            ->post(route('admin.batches.dispose', $batch), ['quantity' => 2, 'type' => 'sale', 'reason' => 'Dropped'])
            ->assertSessionHasErrors('type');
        $this->actingAs($manager)
            ->post(route('admin.batches.dispose', $batch), ['quantity' => 2, 'type' => 'damage', 'reason' => 'Dropped'])
            ->assertSessionHas('success');

        $this->assertSame(3, $batch->fresh()->quantity);
        $this->assertSame(-2, InventoryMovement::query()->where('type', InventoryMovementType::Damage)->sole()->quantity);
    }

    public function test_partial_transfer_redirects_to_the_new_batch(): void
    {
        $item = $this->item();
        $batch = $this->receive($item, 10, '2026-09-01');

        $response = $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->post(route('admin.batches.transfer', $batch), ['quantity' => 3, 'location' => 'Packing area']);

        $moved = InventoryBatch::query()->whereKeyNot($batch->id)->sole();
        $response->assertRedirect(route('admin.batches.show', $moved));
        $this->assertSame(['Packing area', 3], [$moved->location, $moved->quantity]);
        $this->assertSame(7, $batch->fresh()->quantity);
    }

    public function test_untracked_stock_is_adjusted_directly_but_never_below_zero(): void
    {
        $item = InventoryItem::factory()->untracked(2)->create();
        $manager = $this->staff(RoleSlug::SuperAdmin);

        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $item), ['delta' => -3, 'reason' => 'Count'])
            ->assertSessionHas('error', __('admin.inventory.errors.negative'));
        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $item), ['delta' => 5, 'reason' => 'Found in back room'])
            ->assertSessionHas('success');

        $this->assertSame(7, $item->fresh()->quantity_on_hand);
    }

    public function test_batch_tracking_cannot_be_switched_off_while_stock_is_on_hand(): void
    {
        $item = $this->item();
        $this->receive($item, 1, '2026-09-01');

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->put(route('admin.inventory.update', $item), ['low_stock_threshold' => 8, 'track_batches' => '0'])
            ->assertSessionHas('error', __('admin.inventory.errors.tracking_locked'));

        $this->assertTrue($item->fresh()->track_batches);
    }

    public function test_threshold_overrides_the_store_default_for_stock_status(): void
    {
        $item = $this->item();
        $this->receive($item, 8, '2026-09-01');

        $this->actingAs($this->staff(RoleSlug::SuperAdmin))
            ->put(route('admin.inventory.update', $item), ['low_stock_threshold' => 10, 'track_batches' => '1'])
            ->assertSessionHas('success');

        $this->assertSame(1, InventoryItem::query()->withSellableQuantity()->whereStockStatus(StockStatus::LowStock)->count());
    }

    private function item(): InventoryItem
    {
        return ProductVariant::factory()->create()->inventoryItem;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function receive(InventoryItem $item, int $quantity, string $expiresAt, array $attributes = []): InventoryBatch
    {
        return app(InventoryService::class)->receive($item, $quantity, ['expires_at' => $expiresAt, ...$attributes], actor: User::factory()->create());
    }
}
