<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CartItemRequest;
use App\Models\ProductVariant;
use App\Services\Catalog\WishlistService;
use App\Services\Checkout\CartService;
use App\Services\Checkout\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(CartService $cart, PricingService $pricing): View
    {
        $quote = $pricing->quote($cart->quantities());

        $unavailable = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $quote['unavailable'])
            ->get();

        return view('shop.cart.index', [
            'quote' => $quote,
            'unavailable' => $unavailable,
        ]);
    }

    public function store(CartItemRequest $request, CartService $cart, WishlistService $wishlist): RedirectResponse
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($request->integer('variant_id'));

        try {
            $cart->add($variant, $request->integer('quantity'));
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $product = $variant->product;
        $removedFromWishlist = $product !== null
            && feature('wishlist_enabled')
            && $wishlist->contains($product);

        if ($removedFromWishlist) {
            $wishlist->remove($product);
        }

        return back()->with('success', __($removedFromWishlist ? 'shop.wishlist.moved' : 'shop.cart.added'));
    }

    public function update(Request $request, ProductVariant $variant, CartService $cart): RedirectResponse
    {
        $quantity = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ])['quantity'];

        try {
            $cart->set($variant, $quantity);
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('shop.cart.updated'));
    }

    public function destroy(ProductVariant $variant, CartService $cart): RedirectResponse
    {
        $cart->remove($variant);

        return back()->with('success', __('shop.cart.removed'));
    }
}
