<?php

use App\Services\Catalog\HalalCertificationService;
use App\Services\Inventory\InventoryAlertService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('halal:check-expiry', function (HalalCertificationService $service): void {
    $count = $service->sendExpiryAlerts();
    $this->info("Expiry alerts sent for {$count} certificate(s).");
})->purpose('Alert staff about halal certificates that are expiring or have expired');

Artisan::command('inventory:check', function (InventoryAlertService $service): void {
    $result = $service->run();
    $this->info("Marked {$result['expired']} batch(es) expired; expiry alerts for {$result['expiring_alerts']} batch(es); low-stock alerts for {$result['low_stock_alerts']} item(s).");
})->purpose('Flag expired batches and alert staff about expiring and low stock');

Schedule::command('halal:check-expiry')->dailyAt('08:00')->timezone(config('app.display_timezone'))->onOneServer()->withoutOverlapping();
Schedule::command('inventory:check')->dailyAt('07:30')->timezone(config('app.display_timezone'))->onOneServer()->withoutOverlapping();
