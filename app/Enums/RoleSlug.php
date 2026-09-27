<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum RoleSlug: string
{
    use HasLabel;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case OrderManager = 'order_manager';
    case ProductManager = 'product_manager';
    case InventoryManager = 'inventory_manager';
    case ContentManager = 'content_manager';
    case SupportAgent = 'support_agent';
    case Customer = 'customer';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
