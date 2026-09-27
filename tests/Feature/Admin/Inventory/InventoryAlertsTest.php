<?php

namespace Tests\Feature\Admin\Inventory;

use App\Enums\BatchStatus;
use App\Enums\RoleSlug;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Notifications\InventoryExpiryDigest;
use App\Notifications\LowStockDigest;
use App\Services\Inventory\InventoryAlertService;
use App\Services\Inventory\InventoryService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TaxSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-06-15 07:30', 'Asia/Tokyo'));
    }

    public function test_each_expiry_threshold_is_reported_once_per_batch(): void
    {
        $manager = $this->staff(RoleSlug::SuperAdmin);
        $support = $this->customer();
        $batch = $this->receive($this->item(), 20, '2026-06-25');
        Notification::fake();

        $this->alerts()->sendExpiryAlerts();
        $this->alerts()->sendExpiryAlerts();

        Notification::assertSentToTimes($manager, InventoryExpiryDigest::class, 1);
        Notification::assertNotSentTo($support, InventoryExpiryDigest::class);
        $this->assertSame(14, $batch->fresh()->last_alert_days);

        $this->travelTo(CarbonImmutable::parse('2026-06-19 07:30', 'Asia/Tokyo'));
        $this->alerts()->sendExpiryAlerts();

        Notification::assertSentToTimes($manager, InventoryExpiryDigest::class, 2);
        $this->assertSame(7, $batch->fresh()->last_alert_days);
    }

    public function test_daily_check_stops_sales_of_expired_batches_and_reports_them(): void
    {
        $manager = $this->staff(RoleSlug::SuperAdmin);
        $item = $this->item();
        $batch = $this->receive($item, 5, '2026-06-20');
        $batch->forceFill(['expires_at' => '2026-06-13', 'last_alert_days' => 3])->save();
        Notification::fake();

        $this->artisan('inventory:check')->assertSuccessful();

        $this->assertSame(BatchStatus::Expired, $batch->fresh()->status);
        $this->assertSame(0, $batch->fresh()->last_alert_days);
        Notification::assertSentTo($manager, InventoryExpiryDigest::class, fn (InventoryExpiryDigest $digest) => $digest->toArray($manager)['batch_ids'] === [$batch->id]);
    }

    public function test_low_stock_is_reported_once_until_the_item_is_restocked(): void
    {
        $manager = $this->staff(RoleSlug::SuperAdmin);
        $item = $this->item();
        $batch = $this->receive($item, 2, '2026-12-01');
        Notification::fake();

        $this->assertSame(1, $this->alerts()->sendLowStockAlerts());
        $this->assertSame(0, $this->alerts()->sendLowStockAlerts());
        Notification::assertSentToTimes($manager, LowStockDigest::class, 1);

        app(InventoryService::class)->adjust($item, 20, 'Restocked', $batch);
        $this->alerts()->sendLowStockAlerts();
        $this->assertNull($item->fresh()->low_stock_notified_at);

        app(InventoryService::class)->adjust($item, -19, 'Damaged', $batch->fresh());
        $this->assertSame(1, $this->alerts()->sendLowStockAlerts());
        Notification::assertSentToTimes($manager, LowStockDigest::class, 2);
    }

    public function test_draft_products_do_not_raise_low_stock_alerts(): void
    {
        $this->staff(RoleSlug::SuperAdmin);
        Product::factory()->draft()->create();
        Notification::fake();

        $this->assertSame(0, $this->alerts()->sendLowStockAlerts());

        Notification::assertNothingSent();
    }

    public function test_alerts_are_also_mailed_to_the_configured_address(): void
    {
        app(SettingsService::class)->set('notifications', 'admin_alert_email', 'stock-alerts@example.com');
        $this->receive($this->item(), 1, '2026-06-18');
        Notification::fake();

        $this->alerts()->run();

        Notification::assertSentOnDemand(InventoryExpiryDigest::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'stock-alerts@example.com' && $channels === ['mail']);
        Notification::assertSentOnDemand(LowStockDigest::class);
    }

    public function test_disabled_alert_types_are_not_sent(): void
    {
        $manager = $this->staff(RoleSlug::SuperAdmin);
        app(SettingsService::class)->set('notifications', 'expiry_alerts', false);
        app(SettingsService::class)->set('notifications', 'low_stock_alerts', false);
        $batch = $this->receive($this->item(), 1, '2026-06-18');
        Notification::fake();

        $this->alerts()->run();

        Notification::assertNotSentTo($manager, InventoryExpiryDigest::class);
        Notification::assertNotSentTo($manager, LowStockDigest::class);
        $this->assertNull($batch->fresh()->last_alert_days);
    }

    private function item(): InventoryItem
    {
        return Product::factory()->create()->variants()->sole()->inventoryItem;
    }

    private function receive(InventoryItem $item, int $quantity, string $expiresAt): InventoryBatch
    {
        return app(InventoryService::class)->receive($item, $quantity, ['expires_at' => $expiresAt]);
    }

    private function alerts(): InventoryAlertService
    {
        return app(InventoryAlertService::class);
    }
}
