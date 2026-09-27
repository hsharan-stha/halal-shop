<?php

/*
|--------------------------------------------------------------------------
| Permission Catalogue
|--------------------------------------------------------------------------
|
| Source of truth for granular permissions and default role grants. Run
| `php artisan db:seed --class=RolePermissionSeeder` after changing it.
| SUPER_ADMIN implicitly holds every permission.
|
*/

return [

    'permissions' => [
        'dashboard' => ['dashboard.view'],
        'products' => ['products.view', 'products.create', 'products.update', 'products.delete'],
        'categories' => ['categories.view', 'categories.manage'],
        'brands' => ['brands.view', 'brands.manage'],
        'halal_certificates' => ['halal_certificates.view', 'halal_certificates.manage', 'halal_certificates.verify'],
        'inventory' => ['inventory.view', 'inventory.adjust', 'inventory.receive'],
        'suppliers' => ['suppliers.view', 'suppliers.manage'],
        'purchase_orders' => ['purchase_orders.view', 'purchase_orders.manage'],
        'orders' => ['orders.view', 'orders.update', 'orders.cancel', 'orders.refund'],
        'customers' => ['customers.view', 'customers.update', 'customers.delete'],
        'coupons' => ['coupons.view', 'coupons.manage'],
        'reviews' => ['reviews.view', 'reviews.moderate'],
        'support' => ['support.view', 'support.reply'],
        'content' => ['content.view', 'content.manage'],
        'shipping' => ['shipping.view', 'shipping.manage'],
        'tax' => ['tax.view', 'tax.manage'],
        'analytics' => ['analytics.view', 'reports.export'],
        'settings' => ['settings.view', 'settings.update'],
        'shops' => ['shops.view', 'shops.manage'],
        'staff' => ['staff.view', 'staff.manage', 'roles.manage'],
        'audit' => ['audit_logs.view'],
        'system' => ['system.health'],
    ],

    'roles' => [
        'super_admin' => ['*'],

        /*
         * A halal shop runs its own catalogue, stock and purchasing. Orders are
         * read-only: the super admin confirms payment, shipment and cancellation.
         */
        'halal_shop' => [
            'dashboard.view', 'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.view', 'categories.manage', 'brands.view', 'brands.manage',
            'halal_certificates.view', 'halal_certificates.manage',
            'inventory.view', 'inventory.adjust', 'inventory.receive',
            'suppliers.view', 'suppliers.manage', 'purchase_orders.view', 'purchase_orders.manage',
            'orders.view',
        ],

        'customer' => [],
    ],
];
