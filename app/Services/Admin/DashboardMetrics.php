<?php

namespace App\Services\Admin;

use App\Enums\CertificationStatus;
use App\Enums\HalalStatus;
use App\Enums\ProductStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockStatus;
use App\Models\HalalCertification;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Aggregates dashboard figures. Each module contributes its own cards/alerts,
 * limited to what the viewing staff member is permitted to see.
 */
class DashboardMetrics
{
    /**
     * @return list<array{key: string, value: string|int, route?: string|null, tone?: string}>
     */
    public function cards(User $user): array
    {
        $cards = [];

        if ($user->can('customers.view')) {
            $startOfMonth = now(config('app.display_timezone'))->startOfMonth()->utc();
            $cards[] = ['key' => 'customers', 'value' => User::query()->customers()->count(), 'route' => null];
            $cards[] = ['key' => 'new_customers', 'value' => User::query()->customers()->where('created_at', '>=', $startOfMonth)->count(), 'route' => null];
        }

        if ($user->can('products.view')) {
            $cards[] = ['key' => 'active_products', 'value' => Product::query()->where('status', ProductStatus::Active)->count(), 'route' => route('admin.products.index', ['status' => 'active'])];
            $cards[] = ['key' => 'draft_products', 'value' => Product::query()->where('status', ProductStatus::Draft)->count(), 'route' => route('admin.products.index', ['status' => 'draft'])];
        }

        if ($user->can('halal_certificates.view')) {
            $cards[] = ['key' => 'certified_products', 'value' => Product::query()->certifiedHalal()->count(), 'route' => route('admin.products.index', ['halal' => 'certified'])];
        }

        if ($user->can('inventory.view')) {
            $cards[] = ['key' => 'units_on_hand', 'value' => (int) InventoryItem::query()->sum('quantity_on_hand'), 'route' => route('admin.inventory.index')];
        }

        if ($user->can('purchase_orders.view')) {
            $cards[] = ['key' => 'open_purchase_orders', 'value' => PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived])->count(),
                'route' => route('admin.purchase-orders.index', ['status' => PurchaseOrderStatus::Ordered->value])];
        }

        return $cards;
    }

    /**
     * @return list<array{key: string, count: int, route: string|null, tone: string, icon?: string}>
     */
    public function alerts(User $user): array
    {
        $alerts = [];

        if ($user->can('halal_certificates.view')) {
            $today = HalalCertification::today();
            $warningDays = (int) (collect(settings('halal.certificate_expiry_warning_days'))->max() ?? 30);

            $alerts[] = ['key' => 'certificates_expired', 'tone' => 'danger', 'icon' => 'shield', 'route' => route('admin.halal-certifications.index', ['expiry' => 'expired']),
                'count' => HalalCertification::query()->where('status', CertificationStatus::Verified)->whereDate('expires_at', '<', $today)->count()];
            $alerts[] = ['key' => 'certificates_expiring', 'tone' => 'warning', 'icon' => 'clock', 'route' => route('admin.halal-certifications.index', ['expiry' => 'expiring']),
                'count' => HalalCertification::query()->expiringWithin($warningDays)->count()];
            $alerts[] = ['key' => 'certificates_pending', 'tone' => 'info', 'icon' => 'shield-check', 'route' => route('admin.halal-certifications.index', ['status' => 'pending']),
                'count' => HalalCertification::query()->where('status', CertificationStatus::Pending)->count()];
            $alerts[] = ['key' => 'certified_without_certificate', 'tone' => 'danger', 'icon' => 'alert', 'route' => route('admin.products.index', ['halal' => 'certified']),
                'count' => Product::query()->where('halal_status', HalalStatus::Certified)->whereDoesntHave('halalCertifications', fn ($query) => $query->valid())->count()];
        }

        if ($user->can('inventory.view')) {
            $warningDays = (int) (collect(settings('inventory.expiry_warning_days'))->max() ?? 30);

            $alerts[] = ['key' => 'batches_expired', 'tone' => 'danger', 'icon' => 'trash', 'route' => route('admin.batches.index', ['expiry' => 'expired']),
                'count' => InventoryBatch::query()->expired()->count()];
            $alerts[] = ['key' => 'out_of_stock', 'tone' => 'danger', 'icon' => 'archive', 'route' => route('admin.inventory.index', ['stock' => StockStatus::OutOfStock->value]),
                'count' => InventoryItem::query()->forSellableCatalog()->whereStockStatus(StockStatus::OutOfStock)->count()];
            $alerts[] = ['key' => 'batches_expiring', 'tone' => 'warning', 'icon' => 'clock', 'route' => route('admin.batches.index', ['expiry' => 'expiring']),
                'count' => InventoryBatch::query()->expiringWithin($warningDays)->count()];
            $alerts[] = ['key' => 'low_stock', 'tone' => 'warning', 'icon' => 'layers', 'route' => route('admin.inventory.index', ['stock' => StockStatus::LowStock->value]),
                'count' => InventoryItem::query()->forSellableCatalog()->whereStockStatus(StockStatus::LowStock)->count()];
        }

        if ($user->can('products.view')) {
            $alerts[] = ['key' => 'unreviewed_labels', 'tone' => 'warning', 'icon' => 'document', 'route' => route('admin.products.index', ['label' => 'unreviewed', 'status' => 'active']),
                'count' => Product::query()->where('status', ProductStatus::Active)->whereNull('food_label_reviewed_at')->count()];
        }

        return array_values(array_filter($alerts, fn (array $alert) => $alert['count'] > 0));
    }
}
