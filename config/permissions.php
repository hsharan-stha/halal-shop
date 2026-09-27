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
        'staff' => ['staff.view', 'staff.manage', 'roles.manage'],
        'audit' => ['audit_logs.view'],
        'system' => ['system.health'],
    ],

    'roles' => [
        'super_admin' => ['*'],

        'admin' => [
            'dashboard.*', 'products.*', 'categories.*', 'brands.*', 'halal_certificates.*',
            'inventory.*', 'suppliers.*', 'purchase_orders.*', 'orders.*', 'customers.*',
            'coupons.*', 'reviews.*', 'support.*', 'content.*', 'shipping.*', 'tax.*',
            'analytics.view', 'reports.export', 'settings.view', 'settings.update',
            'staff.view', 'audit_logs.view', 'system.health',
        ],

        'order_manager' => [
            'dashboard.view', 'orders.*', 'customers.view', 'products.view', 'inventory.view',
            'shipping.view', 'support.view', 'reports.export',
        ],

        'product_manager' => [
            'dashboard.view', 'products.*', 'categories.*', 'brands.*', 'halal_certificates.view',
            'halal_certificates.manage', 'inventory.view', 'suppliers.view', 'reviews.*', 'tax.view',
        ],

        'inventory_manager' => [
            'dashboard.view', 'products.view', 'inventory.*', 'suppliers.*', 'purchase_orders.*',
            'halal_certificates.view', 'reports.export',
        ],

        'content_manager' => [
            'dashboard.view', 'content.*', 'products.view', 'categories.view', 'brands.view',
        ],

        'support_agent' => [
            'dashboard.view', 'support.*', 'orders.view', 'customers.view', 'products.view',
        ],

        'customer' => [],
    ],
];
