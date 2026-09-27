<?php

namespace App\Models\Concerns;

use App\Models\Shop;
use App\Support\ShopAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows a halal shop owns and manages itself. A null shop_id means the row
 * belongs to the platform: every shop may use it, only a super admin may
 * change it. Visibility is enforced by the global scopes registered in
 * AppServiceProvider.
 */
trait BelongsToShop
{
    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isPlatformOwned(): bool
    {
        return $this->shop_id === null;
    }

    public function isManageableByCurrentUser(): bool
    {
        return ShopAccess::mayManage($this->shop_id);
    }

    /**
     * Owning shop shown in admin lists. A halal shop only ever sees its own
     * rows, so the name is only worth showing to someone who sees every shop.
     */
    public function ownerShopName(): ?string
    {
        return ShopAccess::$tenantId === null ? $this->shop?->name : null;
    }
}
