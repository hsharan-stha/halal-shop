<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\ShopAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class ShopSalesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:orders.view', only: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $shopId = ShopAccess::id($request->user());
        $recorded = fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled);

        $shops = Shop::query()
            ->when($shopId !== null, fn ($query) => $query->whereKey($shopId))
            ->withCount(['orders as orders_count' => $recorded])
            ->withSum(['orders as sales_total' => $recorded], 'total')
            ->withSum(['orders as commission_total' => $recorded], 'commission_amount')
            ->orderByDesc('sales_total')
            ->orderBy('name')
            ->get();

        return view('admin.halal-shops.sales', [
            'shops' => $shops,
            'commissionPercent' => (int) settings('marketplace.commission_percent', 10),
            'canEditCommission' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }

    public function updateCommission(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        settings()->set('marketplace', 'commission_percent', (int) $data['commission_percent']);

        return back()->with('success', __('admin.halal_shops.commission_saved'));
    }
}
