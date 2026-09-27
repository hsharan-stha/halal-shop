<?php

namespace App\Http\Controllers;

use App\Support\ShopAccess;

abstract class Controller
{
    /**
     * Platform rows stay visible to a halal shop so it can use them, but only
     * their owner may change them.
     */
    protected function authorizeShopOwnership(?int $shopId): void
    {
        abort_unless(ShopAccess::mayManage($shopId), 403);
    }
}
