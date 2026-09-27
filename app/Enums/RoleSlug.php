<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum RoleSlug: string
{
    use HasLabel;

    case SuperAdmin = 'super_admin';
    case HalalShop = 'halal_shop';
    case Customer = 'customer';

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }
}
