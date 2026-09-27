<?php

namespace App\Http\Controllers\Shop;

use App\Enums\DeliveryDestination;
use App\Enums\PaymentMethod;
use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Shop;
use App\Services\Checkout\CartService;
use App\Services\Checkout\CheckoutService;
use App\Services\Checkout\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(Request $request, CartService $cart, PricingService $pricing, CheckoutService $checkout): View|RedirectResponse
    {
        $quantities = $cart->quantities();

        if ($quantities === []) {
            return redirect()->route('cart.index')->with('error', __('shop.checkout.errors.empty'));
        }

        $quote = $pricing->quote($quantities);

        if ($quote['lines'] === [] || $quote['unavailable'] !== []) {
            return redirect()->route('cart.index')->with('error', __('shop.checkout.errors.unavailable'));
        }

        $shopIds = collect($quote['lines'])->map(fn (array $line) => (int) $line['variant']->product->shop_id)->unique()->filter();

        if ($shopIds->count() !== 1) {
            return redirect()->route('cart.index')->with('error', __('shop.checkout.errors.mixed_shops'));
        }

        return view('shop.checkout.create', [
            'quote' => $quote,
            'methods' => $checkout->availableMethods(),
            'address' => $request->user()->addresses()->where('is_default', true)->first(),
            'fulfillingShop' => Shop::query()->find($shopIds->first()),
            'shops' => Shop::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout): RedirectResponse
    {
        try {
            $order = $checkout->place(
                $request->user(),
                $request->address(),
                $request->enum('payment_method', PaymentMethod::class),
                $request->validated('customer_note'),
                $request->enum('delivery_to', DeliveryDestination::class) ?? DeliveryDestination::Customer,
                $request->integer('pickup_shop_id') ?: null,
            );
        } catch (CheckoutException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', __('shop.checkout.placed'));
    }
}
