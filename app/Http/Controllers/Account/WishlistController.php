<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(WishlistService $wishlist, StorefrontCatalog $catalog): View
    {
        abort_unless(feature('wishlist_enabled'), 404);

        return view('shop.wishlist.index', [
            'products' => $catalog->productsByIds($wishlist->ids()),
            'wishlistIds' => $wishlist->ids(),
        ]);
    }

    public function store(string $product, WishlistService $wishlist): RedirectResponse
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $wishlist->add($this->product($product));

        return back()->with('success', __('shop.wishlist.added'));
    }

    public function destroy(string $product, WishlistService $wishlist): RedirectResponse
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $wishlist->remove($this->product($product));

        return back()->with('success', __('shop.wishlist.removed'));
    }

    private function product(string $slug): Product
    {
        return Product::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
