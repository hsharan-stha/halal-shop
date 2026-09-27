<?php

namespace Tests\Feature\Admin\Inventory;

use App\Enums\BatchStatus;
use App\Enums\InventoryMovementType;
use App\Exceptions\Inventory\InventoryException;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Tokyo'));
        $this->inventory = app(InventoryService::class);
    }

    public function test_every_variant_gets_a_stock_record(): void
    {
        $variant = ProductVariant::factory()->create();

        $this->assertTrue($variant->inventoryItem()->exists());
        $this->assertSame(0, $variant->inventoryItem->quantity_on_hand);
    }

    public function test_allocation_takes_the_earliest_expiring_batch_first(): void
    {
        [$variant, $item] = $this->variant();
        $later = $this->receive($item, 5, '2026-09-30');
        $sooner = $this->receive($item, 3, '2026-07-10');

        $allocations = $this->inventory->allocate($variant, 5, $this->reference());

        $this->assertSame([
            ['batch_id' => $sooner->id, 'quantity' => 3],
            ['batch_id' => $later->id, 'quantity' => 2],
        ], $allocations);
        $this->assertSame(0, $sooner->fresh()->quantity);
        $this->assertSame(3, $later->fresh()->quantity);
        $this->assertSame(3, $item->fresh()->quantity_on_hand);
        $this->assertSame([-3, -2], InventoryMovement::query()->where('type', InventoryMovementType::Sale)->orderBy('id')->pluck('quantity')->all());
    }

    public function test_expired_short_dated_and_quarantined_batches_are_never_allocated(): void
    {
        [$variant, $item] = $this->variant();
        $expired = $this->receive($item, 10, '2026-06-20');
        $expired->forceFill(['expires_at' => '2026-06-10'])->save();
        $this->receive($item, 10, '2026-06-15');
        $this->receive($item, 10, '2026-12-31', ['status' => BatchStatus::Quarantined]);
        $sellable = $this->receive($item, 2, '2026-12-31');

        $this->assertSame(2, $item->fresh()->sellableQuantity());

        try {
            $this->inventory->allocate($variant, 3, $this->reference());
            $this->fail('Allocation should have been refused.');
        } catch (InventoryException $exception) {
            $this->assertSame(__('admin.inventory.errors.insufficient', ['requested' => 3, 'available' => 2]), $exception->getMessage());
        }

        $this->assertSame(2, $sellable->fresh()->quantity);
        $this->assertSame(32, $item->fresh()->quantity_on_hand);
        $this->assertFalse(InventoryMovement::query()->where('type', InventoryMovementType::Sale)->exists());
    }

    public function test_expiry_is_judged_on_the_japan_calendar_day(): void
    {
        // 15:30 UTC on 15 June is already 00:30 on 16 June in Tokyo.
        $this->travelTo(CarbonImmutable::parse('2026-06-15 15:30', 'UTC'));
        [$variant, $item] = $this->variant();
        $this->receive($item, 4, '2026-06-16');
        $tomorrow = $this->receive($item, 1, '2026-06-17');

        $this->assertSame(1, $item->fresh()->sellableQuantity());
        $this->assertSame([['batch_id' => $tomorrow->id, 'quantity' => 1]], $this->inventory->allocate($variant, 1, $this->reference()));
    }

    public function test_receiving_goods_that_have_already_expired_is_refused(): void
    {
        [, $item] = $this->variant();

        $this->expectExceptionObject(InventoryException::expiredOnReceipt());

        try {
            $this->receive($item, 5, '2026-06-14');
        } finally {
            $this->assertSame(0, $item->fresh()->quantity_on_hand);
            $this->assertFalse(InventoryBatch::query()->exists());
        }
    }

    public function test_cancelled_allocations_return_to_their_original_batches(): void
    {
        [$variant, $item] = $this->variant();
        $batch = $this->receive($item, 5, '2026-09-30');
        $reference = $this->reference();
        $allocations = $this->inventory->allocate($variant, 4, $reference);

        $this->inventory->release($variant, $allocations, $reference);

        $this->assertSame(5, $batch->fresh()->quantity);
        $this->assertSame(5, $item->fresh()->quantity_on_hand);
        $this->assertTrue(InventoryMovement::query()->where('type', InventoryMovementType::CancelledOrder)->where('quantity', 4)->where('inventory_batch_id', $batch->id)->exists());
    }

    public function test_customer_returns_are_held_in_a_separate_quarantined_batch(): void
    {
        [$variant, $item] = $this->variant();
        $batch = $this->receive($item, 5, '2026-09-30');
        $reference = $this->reference();
        $allocations = $this->inventory->allocate($variant, 2, $reference);

        $this->inventory->release($variant, $allocations, $reference, InventoryMovementType::Return);

        $returned = InventoryBatch::query()->whereKeyNot($batch->id)->sole();
        $this->assertSame(BatchStatus::Quarantined, $returned->status);
        $this->assertSame(2, $returned->quantity);
        $this->assertSame(3, $batch->fresh()->quantity);
        $this->assertSame(3, $item->fresh()->sellableQuantity());
        $this->assertSame(5, $item->fresh()->quantity_on_hand);
    }

    public function test_batch_adjustments_cannot_make_stock_negative(): void
    {
        [, $item] = $this->variant();
        $batch = $this->receive($item, 3, '2026-09-30');

        $this->expectExceptionObject(InventoryException::negativeStock());

        try {
            $this->inventory->adjust($item, -4, 'Stock count', $batch);
        } finally {
            $this->assertSame(3, $batch->fresh()->quantity);
            $this->assertSame(3, $item->fresh()->quantity_on_hand);
        }
    }

    public function test_batch_tracked_items_must_be_adjusted_on_a_batch(): void
    {
        [, $item] = $this->variant();

        $this->expectExceptionObject(InventoryException::batchRequired());

        $this->inventory->adjust($item, 5, 'Found stock');
    }

    public function test_untracked_items_go_negative_only_when_the_store_allows_it(): void
    {
        $item = InventoryItem::factory()->untracked(2)->create();

        try {
            $this->inventory->adjust($item, -3, 'Stock count');
            $this->fail('Negative stock should have been refused.');
        } catch (InventoryException) {
            $this->assertSame(2, $item->fresh()->quantity_on_hand);
        }

        app(SettingsService::class)->set('inventory', 'allow_negative_stock', true);
        $this->inventory->adjust($item, -3, 'Stock count');

        $this->assertSame(-1, $item->fresh()->quantity_on_hand);
        $this->assertSame(0, $item->fresh()->sellableQuantity());
    }

    public function test_partial_transfer_splits_the_batch_and_keeps_totals(): void
    {
        [, $item] = $this->variant();
        $batch = $this->receive($item, 10, '2026-09-30', ['location' => 'Shelf A']);

        $moved = $this->inventory->transfer($batch, 4, 'Freezer 2');

        $this->assertNotSame($batch->id, $moved->id);
        $this->assertSame(['Shelf A', 6], [$batch->fresh()->location, $batch->fresh()->quantity]);
        $this->assertSame(['Freezer 2', 4], [$moved->location, $moved->quantity]);
        $this->assertSame($batch->expires_at->toDateString(), $moved->expires_at->toDateString());
        $this->assertSame(10, $item->fresh()->quantity_on_hand);
    }

    public function test_system_notes_are_written_in_the_store_locale_whatever_the_operator_language(): void
    {
        [, $item] = $this->variant();
        $batch = $this->receive($item, 10, '2026-09-30', ['location' => 'Shelf A']);
        app()->setLocale('en');

        $this->inventory->transfer($batch, 10, 'Freezer 2');

        $this->assertSame(
            __('admin.inventory.transfer_note', ['from' => 'Shelf A', 'to' => 'Freezer 2'], 'ja'),
            InventoryMovement::query()->where('type', InventoryMovementType::Transfer)->latest('id')->value('reason'),
        );
    }

    public function test_disposing_a_whole_batch_marks_it_disposed(): void
    {
        [, $item] = $this->variant();
        $batch = $this->receive($item, 6, '2026-09-30');

        $this->inventory->dispose($batch, 6, InventoryMovementType::Damage, 'Crushed in transit');

        $this->assertSame(BatchStatus::Disposed, $batch->fresh()->status);
        $this->assertSame(0, $item->fresh()->quantity_on_hand);
        $this->assertSame(-6, InventoryMovement::query()->where('type', InventoryMovementType::Damage)->sole()->quantity);
    }

    public function test_daily_sweep_marks_only_batches_past_expiry(): void
    {
        [, $item] = $this->variant();
        $old = $this->receive($item, 3, '2026-06-20');
        $old->forceFill(['expires_at' => '2026-06-14'])->save();
        $today = $this->receive($item, 3, '2026-06-15');

        $this->assertSame(1, $this->inventory->markExpiredBatches());

        $this->assertSame(BatchStatus::Expired, $old->fresh()->status);
        $this->assertSame(BatchStatus::Available, $today->fresh()->status);
    }

    public function test_expired_batches_cannot_be_made_available_again(): void
    {
        [, $item] = $this->variant();
        $batch = $this->receive($item, 3, '2026-06-20');
        $batch->forceFill(['expires_at' => '2026-06-14', 'status' => BatchStatus::Expired])->save();

        $this->expectExceptionObject(InventoryException::cannotReleaseExpired());

        $this->inventory->changeBatchStatus($batch, BatchStatus::Available);
    }

    public function test_movements_cannot_be_edited_or_deleted(): void
    {
        [, $item] = $this->variant();
        $this->receive($item, 3, '2026-09-30');
        $movement = InventoryMovement::query()->sole();

        $this->assertSame([0, 3], [$movement->before_quantity, $movement->after_quantity]);
        $this->expectException(LogicException::class);

        $movement->update(['reason' => 'Rewritten']);
    }

    /**
     * @return array{ProductVariant, InventoryItem}
     */
    private function variant(): array
    {
        $variant = ProductVariant::factory()->create();

        return [$variant, $variant->inventoryItem];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function receive(InventoryItem $item, int $quantity, string $expiresAt, array $attributes = []): InventoryBatch
    {
        return $this->inventory->receive($item, $quantity, ['expires_at' => $expiresAt, ...$attributes]);
    }

    private function reference(): User
    {
        return $this->customer();
    }
}
