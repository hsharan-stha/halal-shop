<?php

namespace App\Http\Middleware;

use App\Support\ShopAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits a halal-shop login to its own products, stock, and platform orders
 * for this request only.
 */
class ApplyShopTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        ShopAccess::$tenantId = ShopAccess::id($request->user());

        try {
            return $next($request);
        } finally {
            ShopAccess::$tenantId = null;
        }
    }
}
