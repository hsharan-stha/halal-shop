<?php

namespace App\Services\Inventory;

use App\Enums\StockStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\InventoryExpiryDigest;
use App\Notifications\LowStockDigest;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Daily stock health sweep: flags expired batches and sends one digest per
 * alert type. Each batch threshold and each low-stock episode is reported once.
 */
class InventoryAlertService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return array{expired: int, expiring_alerts: int, low_stock_alerts: int}
     */
    public function run(): array
    {
        return [
            'expired' => $this->inventory->markExpiredBatches(),
            'expiring_alerts' => $this->sendExpiryAlerts(),
            'low_stock_alerts' => $this->sendLowStockAlerts(),
        ];
    }

    public function sendExpiryAlerts(): int
    {
        if (! settings('notifications.expiry_alerts')) {
            return 0;
        }

        $thresholds = collect(settings('inventory.expiry_warning_days'))->map(fn ($days) => (int) $days)->filter(fn (int $days) => $days > 0)->sort()->values();
        $horizon = (int) ($thresholds->max() ?? 0);
        $entries = [];

        InventoryBatch::query()
            ->inStock()
            ->whereDate('expires_at', '<=', local_today()->addDays($horizon)->toDateString())
            ->with('item.variant.product:id,name,japanese_name')
            ->chunkById(200, function (Collection $batches) use ($thresholds, &$entries): void {
                foreach ($batches as $batch) {
                    $days = $batch->daysUntilExpiry();
                    $threshold = $days < 0 ? 0 : $thresholds->first(fn (int $limit) => $days <= $limit);

                    if ($threshold === null || ($batch->last_alert_days !== null && $batch->last_alert_days <= $threshold)) {
                        continue;
                    }

                    $variant = $batch->item->variant;
                    $entries[] = [
                        'batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'product' => $variant?->product?->localizedName() ?? '—',
                        'variant' => $variant?->translate('name'),
                        'sku' => $variant?->sku,
                        'quantity' => $batch->quantity,
                        'expires_at' => $batch->expires_at->toDateString(),
                        'days_left' => $days,
                    ];

                    $batch->forceFill(['last_alert_days' => $threshold])->saveQuietly();
                }
            });

        if ($entries !== []) {
            usort($entries, fn (array $a, array $b) => $a['days_left'] <=> $b['days_left']);
            $this->notify(new InventoryExpiryDigest($entries));
        }

        return count($entries);
    }

    public function sendLowStockAlerts(): int
    {
        InventoryItem::query()->whereNotNull('low_stock_notified_at')->whereStockStatus(StockStatus::InStock)->update(['low_stock_notified_at' => null]);

        if (! settings('notifications.low_stock_alerts')) {
            return 0;
        }

        $items = InventoryItem::query()
            ->forSellableCatalog()
            ->whereNull('low_stock_notified_at')
            ->where(fn ($query) => $query->whereStockStatus(StockStatus::LowStock)->orWhere(fn ($inner) => $inner->whereStockStatus(StockStatus::OutOfStock)))
            ->withSellableQuantity()
            ->with('variant.product:id,name,japanese_name')
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $entries = $items->map(fn (InventoryItem $item) => [
            'item_id' => $item->id,
            'product' => $item->variant?->product?->localizedName() ?? '—',
            'variant' => $item->variant?->translate('name'),
            'sku' => $item->variant?->sku,
            'sellable' => $item->sellableQuantity(),
            'threshold' => $item->lowStockThreshold(),
        ])->sortBy('sellable')->values()->all();

        $this->notify(new LowStockDigest($entries));
        InventoryItem::query()->whereKey($items->modelKeys())->update(['low_stock_notified_at' => now()]);

        return count($entries);
    }

    private function notify(BaseNotification $notification): void
    {
        Notification::send($this->recipients(), $notification);

        if (filled($email = settings('notifications.admin_alert_email'))) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(): Collection
    {
        return User::query()->staff()->where('status', 'active')->with('roles.permissions')->get()
            ->filter(fn (User $user) => $user->hasPermission('inventory.view'))
            ->values();
    }
}
