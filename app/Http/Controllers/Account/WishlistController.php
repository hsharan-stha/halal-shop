<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\StorefrontCatalog;
use App\Services\Catalog\WishlistService;
use App\Services\Checkout\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(WishlistService $wishlist, StorefrontCatalog $catalog): View
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $products = $catalog->productsByIds($wishlist->ids());

        if ($products->isNotEmpty()) {
            $products->load([
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with(['inventoryItem' => fn ($items) => $items->withSellableQuantity()]),
            ]);
        }

        return view('shop.wishlist.index', [
            'products' => $products,
            'wishlistIds' => $wishlist->ids(),
        ]);
    }

    public function moveToCart(Request $request, string $product, WishlistService $wishlist, CartService $cart): RedirectResponse
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $product = $this->product($product);
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $variant = $product->variants()->where('is_active', true)->whereKey($data['variant_id'])->firstOrFail();

        try {
            $cart->add($variant, $data['quantity']);
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $wishlist->remove($product);

        return back()->with('success', __('shop.wishlist.moved'));
    }

    public function store(Request $request, string $product, WishlistService $wishlist): JsonResponse|RedirectResponse
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $wishlist->add($this->product($product));

        return $this->saved($request, $wishlist, true, __('shop.wishlist.added'));
    }

    public function destroy(Request $request, string $product, WishlistService $wishlist): JsonResponse|RedirectResponse
    {
        abort_unless(feature('wishlist_enabled'), 404);

        $wishlist->remove($this->product($product));

        return $this->saved($request, $wishlist, false, __('shop.wishlist.removed'));
    }

    private function saved(Request $request, WishlistService $wishlist, bool $wished, string $message): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        $count = count($wishlist->ids());

        return response()->json([
            'wished' => $wished,
            'count' => $count,
            'count_label' => trans_choice('shop.wishlist.count', $count, ['count' => $count]),
            'nav_label' => $count > 0
                ? trans_choice('shop.wishlist.count', $count, ['count' => $count])
                : __('shop.nav.wishlist'),
            'message' => $message,
        ]);
    }

    private function product(string $slug): Product
    {
        return Product::query()->published()->where('slug', $slug)->firstOrFail();
    }
}
