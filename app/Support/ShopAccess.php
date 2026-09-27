<?php

namespace App\Support;

use App\Enums\RoleSlug;
use App\Models\User;

/**
 * A halal-shop login may only see its own shop. Super admin and other staff
 * are not limited.
 */
class ShopAccess
{
    /**
     * Set for the current admin request when the user may only see one shop.
     */
    public static ?int $tenantId = null;

    public static function id(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $user->loadMissing('roles');

        if ($user->isSuperAdmin() || ! $user->hasRole(RoleSlug::HalalShop)) {
            return null;
        }

        return $user->shop_id === null ? 0 : (int) $user->shop_id;
    }

    /**
     * Whether the current request may change a row with this owner. Rows with
     * no owner belong to the platform and only a super admin may change them.
     */
    public static function mayManage(?int $shopId): bool
    {
        if (self::$tenantId === null) {
            return true;
        }

        return $shopId !== null && (int) $shopId === self::$tenantId;
    }
}
