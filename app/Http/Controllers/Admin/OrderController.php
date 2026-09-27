<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\Checkout\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:orders.view', only: ['index', 'show']),
            new Middleware('can:orders.update', only: ['pay', 'ship', 'complete']),
            new Middleware('can:orders.cancel', only: ['cancel']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);

        $orders = Order::query()
            ->with(['customer:id,name,email,deleted_at', 'shop:id,name'])
            ->withCount('items')
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('order_number', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', function (Builder $query) use ($term): void {
                            $query->where('name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%');
                        });
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('placed_at')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'items', 'shop', 'pickupShop']);

        return view('admin.orders.show', ['order' => $order]);
    }

    public function pay(Request $request, Order $order, CheckoutService $checkout): RedirectResponse
    {
        return $this->act($order, fn () => $checkout->markPaid($order, $request->user()), 'admin.orders.marked_paid');
    }

    public function ship(Order $order, CheckoutService $checkout): RedirectResponse
    {
        return $this->act($order, fn () => $checkout->markShipped($order), 'admin.orders.shipped');
    }

    public function complete(Order $order, CheckoutService $checkout): RedirectResponse
    {
        return $this->act($order, fn () => $checkout->markCompleted($order), 'admin.orders.completed');
    }

    public function cancel(Request $request, Order $order, CheckoutService $checkout): RedirectResponse
    {
        return $this->act($order, fn () => $checkout->cancel($order, $request->user()), 'admin.orders.cancelled');
    }

    private function act(Order $order, callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (CheckoutException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __($message));
    }
}
