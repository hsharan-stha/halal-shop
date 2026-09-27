<?php

namespace App\Http\Controllers\Shop;

use App\Enums\PaymentMethod;
use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
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

        return view('shop.checkout.create', [
            'quote' => $quote,
            'methods' => $checkout->availableMethods(),
            'address' => $request->user()->addresses()->where('is_default', true)->first(),
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
            );
        } catch (CheckoutException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('account.orders.show', $order)
            ->with('success', __('shop.checkout.placed'));
    }
}
