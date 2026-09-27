<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Catalog\RecentlyViewed;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(StorefrontCatalog $catalog, RecentlyViewed $recentlyViewed, WishlistService $wishlist): View
    {
        return view('shop.home', [
            ...$catalog->home(),
            'recent' => $recentlyViewed->products(),
            'wishlistIds' => $wishlist->ids(),
        ]);
    }
}
