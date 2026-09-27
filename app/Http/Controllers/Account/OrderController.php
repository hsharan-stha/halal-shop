<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            ->latest('placed_at')
            ->paginate(config('shop.pagination.shop'));

        return view('shop.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->owns($request, $order);

        $order->load('items');

        return view('shop.orders.show', [
            'order' => $order,
            'bankInstructions' => $order->payment_method->value === 'bank_transfer'
                ? settings()->translated('payment.bank_transfer_instructions')
                : '',
        ]);
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout): RedirectResponse
    {
        $this->owns($request, $order);
        abort_unless($order->customerMayCancel(), 403);

        try {
            $checkout->cancel($order, $request->user());
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('shop.orders.cancelled'));
    }

    private function owns(Request $request, Order $order): void
    {
        abort_unless((string) $order->user_id === (string) $request->user()->id, 404);
    }
}
