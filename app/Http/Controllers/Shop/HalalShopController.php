<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HalalShopController extends Controller
{
    public function index(Request $request, StorefrontCatalog $catalog): View
    {
        $term = trim((string) $request->string('q'));

        return view('shop.halal-shops.index', [
            'shops' => $catalog->shops($term === '' ? null : $term),
            'term' => $term,
            'latitude' => session('customer_latitude'),
            'longitude' => session('customer_longitude'),
        ]);
    }

    public function show(Shop $shop, StorefrontCatalog $catalog, WishlistService $wishlist): View
    {
        abort_unless($shop->is_active, 404);

        $products = $catalog->products(['shop' => $shop->slug, 'sort' => session()->has('customer_latitude') ? 'nearest' : 'newest']);

        return view('shop.halal-shops.show', [
            'shop' => $shop,
            'products' => $products,
            'wishlistIds' => $wishlist->ids(),
            'distance' => $shop->distanceKm(
                is_numeric(session('customer_latitude')) ? (float) session('customer_latitude') : null,
                is_numeric(session('customer_longitude')) ? (float) session('customer_longitude') : null,
            ),
        ]);
    }

    public function location(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $request->session()->put('customer_latitude', (float) $data['latitude']);
        $request->session()->put('customer_longitude', (float) $data['longitude']);

        return back();
    }
}
