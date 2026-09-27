<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Central definition of storefront, account and admin navigation. Items whose
 * route is not registered, whose feature flag is off, or which the user is not
 * permitted to access are omitted, so no dead links are rendered.
 */
class Navigation
{
    /**
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    public static function bottom(): array
    {
        return self::filter([
            ['label' => 'shop.nav.home', 'route' => 'home', 'icon' => 'home', 'active' => 'home'],
            ['label' => 'shop.nav.categories', 'route' => 'categories.index', 'icon' => 'squares', 'active' => 'categories.*'],
            ['label' => 'shop.nav.search', 'route' => 'search', 'icon' => 'search', 'active' => 'search'],
            ['label' => 'shop.nav.cart', 'route' => 'cart.index', 'icon' => 'cart', 'active' => 'cart.*'],
            ['label' => 'shop.nav.account', 'route' => 'account.dashboard', 'icon' => 'user', 'active' => 'account.*'],
        ]);
    }

    /**
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    public static function account(): array
    {
        return self::filter([
            ['label' => 'shop.account.nav.dashboard', 'route' => 'account.dashboard', 'icon' => 'home', 'active' => 'account.dashboard'],
            ['label' => 'shop.account.nav.orders', 'route' => 'account.orders.index', 'icon' => 'receipt', 'active' => 'account.orders.*'],
            ['label' => 'shop.account.nav.addresses', 'route' => 'account.addresses.index', 'icon' => 'map-pin', 'active' => 'account.addresses.*'],
            ['label' => 'shop.account.nav.wishlist', 'route' => 'account.wishlist', 'icon' => 'heart', 'active' => 'account.wishlist', 'feature' => 'wishlist_enabled'],
            ['label' => 'shop.account.nav.reviews', 'route' => 'account.reviews', 'icon' => 'star', 'active' => 'account.reviews', 'feature' => 'reviews_enabled'],
            ['label' => 'shop.account.nav.coupons', 'route' => 'account.coupons', 'icon' => 'ticket', 'active' => 'account.coupons', 'feature' => 'coupons_enabled'],
            ['label' => 'shop.account.nav.notifications', 'route' => 'account.notifications', 'icon' => 'bell', 'active' => 'account.notifications'],
            ['label' => 'shop.account.nav.support', 'route' => 'account.support.index', 'icon' => 'chat', 'active' => 'account.support.*'],
            ['label' => 'shop.account.nav.profile', 'route' => 'account.profile', 'icon' => 'user', 'active' => 'account.profile'],
            ['label' => 'shop.account.nav.security', 'route' => 'account.security', 'icon' => 'lock', 'active' => 'account.security'],
            ['label' => 'shop.account.nav.settings', 'route' => 'account.settings', 'icon' => 'cog', 'active' => 'account.settings'],
            ['label' => 'shop.account.nav.privacy', 'route' => 'account.privacy', 'icon' => 'shield', 'active' => 'account.privacy'],
        ]);
    }

    /**
     * @return list<array{heading: string, items: list<array<string, string>>}>
     */
    public static function admin(?User $user): array
    {
        $user?->loadMissing('roles');

        $sections = [
            ['heading' => 'admin.nav.overview', 'items' => [
                ['label' => 'admin.nav.dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'active' => 'admin.dashboard', 'can' => 'dashboard.view'],
                ['label' => 'admin.nav.analytics', 'route' => 'admin.analytics.index', 'icon' => 'chart', 'active' => 'admin.analytics.*', 'can' => 'analytics.view'],
            ]],
            ['heading' => 'admin.nav.sales', 'items' => [
                ['label' => 'admin.nav.orders', 'route' => 'admin.orders.index', 'icon' => 'receipt', 'active' => 'admin.orders.*', 'can' => 'orders.view'],
                ['label' => 'admin.nav.halal_shops', 'route' => 'admin.halal-shops.index', 'icon' => 'building', 'active' => 'admin.halal-shops.*', 'can' => 'shops.manage'],
                ['label' => 'admin.nav.shop_sales', 'route' => 'admin.shop-sales.index', 'icon' => 'chart', 'active' => 'admin.shop-sales.*', 'can' => 'orders.view'],
                ['label' => 'admin.nav.my_shop', 'route' => 'admin.my-shop.edit', 'icon' => 'map-pin', 'active' => 'admin.my-shop.*', 'role' => 'halal_shop'],
                ['label' => 'admin.nav.refunds', 'route' => 'admin.refunds.index', 'icon' => 'banknotes', 'active' => 'admin.refunds.*', 'can' => 'orders.view'],
                ['label' => 'admin.nav.customers', 'route' => 'admin.customers.index', 'icon' => 'users', 'active' => 'admin.customers.*', 'can' => 'customers.view'],
                ['label' => 'admin.nav.coupons', 'route' => 'admin.coupons.index', 'icon' => 'ticket', 'active' => 'admin.coupons.*', 'can' => 'coupons.view'],
            ]],
            ['heading' => 'admin.nav.catalog', 'items' => [
                ['label' => 'admin.nav.products', 'route' => 'admin.products.index', 'icon' => 'cube', 'active' => 'admin.products.*', 'can' => 'products.view'],
                ['label' => 'admin.nav.categories', 'route' => 'admin.categories.index', 'icon' => 'squares', 'active' => 'admin.categories.*', 'can' => 'categories.view'],
                ['label' => 'admin.nav.brands', 'route' => 'admin.brands.index', 'icon' => 'tag', 'active' => 'admin.brands.*', 'can' => 'brands.view'],
                ['label' => 'admin.nav.halal', 'route' => 'admin.halal-certifications.index', 'icon' => 'shield-check', 'active' => 'admin.halal-certifications.*', 'can' => 'halal_certificates.view'],
                ['label' => 'admin.nav.reviews', 'route' => 'admin.reviews.index', 'icon' => 'star', 'active' => 'admin.reviews.*', 'can' => 'reviews.view'],
                ['label' => 'admin.nav.questions', 'route' => 'admin.questions.index', 'icon' => 'question', 'active' => 'admin.questions.*', 'can' => 'reviews.view'],
            ]],
            ['heading' => 'admin.nav.stock', 'items' => [
                ['label' => 'admin.nav.inventory', 'route' => 'admin.inventory.index', 'icon' => 'archive', 'active' => 'admin.inventory.*', 'can' => 'inventory.view'],
                ['label' => 'admin.nav.batches', 'route' => 'admin.batches.index', 'icon' => 'layers', 'active' => 'admin.batches.*', 'can' => 'inventory.view'],
                ['label' => 'admin.nav.suppliers', 'route' => 'admin.suppliers.index', 'icon' => 'building', 'active' => 'admin.suppliers.*', 'can' => 'suppliers.view'],
                ['label' => 'admin.nav.purchase_orders', 'route' => 'admin.purchase-orders.index', 'icon' => 'clipboard', 'active' => 'admin.purchase-orders.*', 'can' => 'purchase_orders.view'],
            ]],
            ['heading' => 'admin.nav.operations', 'items' => [
                ['label' => 'admin.nav.shipping', 'route' => 'admin.shipping.index', 'icon' => 'truck', 'active' => 'admin.shipping.*', 'can' => 'shipping.view'],
                ['label' => 'admin.nav.tax', 'route' => 'admin.tax.index', 'icon' => 'receipt', 'active' => 'admin.tax.*', 'can' => 'tax.view'],
                ['label' => 'admin.nav.support', 'route' => 'admin.support.index', 'icon' => 'chat', 'active' => 'admin.support.*', 'can' => 'support.view'],
                ['label' => 'admin.nav.exports', 'route' => 'admin.exports.index', 'icon' => 'download', 'active' => 'admin.exports.*', 'can' => 'reports.export'],
            ]],
            ['heading' => 'admin.nav.content', 'items' => [
                ['label' => 'admin.nav.homepage', 'route' => 'admin.homepage.index', 'icon' => 'home', 'active' => 'admin.homepage.*', 'can' => 'content.view'],
                ['label' => 'admin.nav.banners', 'route' => 'admin.banners.index', 'icon' => 'photo', 'active' => 'admin.banners.*', 'can' => 'content.view'],
                ['label' => 'admin.nav.pages', 'route' => 'admin.pages.index', 'icon' => 'document', 'active' => 'admin.pages.*', 'can' => 'content.view'],
            ]],
            ['heading' => 'admin.nav.system', 'items' => [
                ['label' => 'admin.nav.settings', 'route' => 'admin.settings.index', 'icon' => 'cog', 'active' => 'admin.settings.*', 'can' => 'settings.view'],
                ['label' => 'admin.nav.staff', 'route' => 'admin.staff.index', 'icon' => 'users', 'active' => 'admin.staff.*', 'can' => 'staff.view'],
                ['label' => 'admin.nav.roles', 'route' => 'admin.roles.index', 'icon' => 'lock', 'active' => 'admin.roles.*', 'can' => 'staff.view'],
                ['label' => 'admin.nav.audit_logs', 'route' => 'admin.audit-logs.index', 'icon' => 'clipboard', 'active' => 'admin.audit-logs.*', 'can' => 'audit_logs.view'],
                ['label' => 'admin.nav.health', 'route' => 'admin.system.health', 'icon' => 'heart-pulse', 'active' => 'admin.system.*', 'can' => 'system.health'],
            ]],
        ];

        $result = [];

        foreach ($sections as $section) {
            $items = array_values(array_filter(
                self::filter($section['items']),
                fn (array $item) => (! isset($item['can']) || ($user && $user->can($item['can'])))
                    && (! isset($item['role']) || ($user && $user->hasRole($item['role']))),
            ));

            if ($items !== []) {
                $result[] = ['heading' => $section['heading'], 'items' => $items];
            }
        }

        return $result;
    }

    /**
     * @param  list<array<string, string>>  $items
     * @return list<array<string, string>>
     */
    private static function filter(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item) => Route::has($item['route']) && (! isset($item['feature']) || feature($item['feature'])),
        ));
    }
}
