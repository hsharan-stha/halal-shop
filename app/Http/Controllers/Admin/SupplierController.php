<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Support\ShopAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:suppliers.view', only: ['index', 'show']),
            new Middleware('can:suppliers.manage', except: ['index', 'show']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $suppliers = Supplier::query()
            ->with('shop:id,name')
            ->withCount(['supplierProducts', 'purchaseOrders'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('company_name', 'like', '%'.$q.'%')
                ->orWhere('code', 'like', '%'.$q.'%')
                ->orWhere('contact_name', 'like', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers', 'filters'));
    }

    public function create(): View
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'country_code' => 'JP'])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->attributesForModel() + ['shop_id' => ShopAccess::$tenantId]);

        return redirect()->route('admin.suppliers.show', $supplier)->with('success', __('admin.suppliers.created'));
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load([
            'supplierProducts' => fn ($query) => $query->with('variant.product:id,name,japanese_name,deleted_at')->orderByDesc('is_preferred')->orderBy('id'),
        ]);

        return view('admin.suppliers.show', [
            'supplier' => $supplier,
            'purchaseOrders' => $supplier->purchaseOrders()->withCount('items')->latest('id')->limit(10)->get(),
            'variantOptions' => array_diff_key(ProductVariant::selectOptions(), $supplier->supplierProducts->pluck('product_variant_id')->flip()->all()),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorizeShopOwnership($supplier->shop_id);

        return view('admin.suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeShopOwnership($supplier->shop_id);

        $supplier->update($request->attributesForModel());

        return redirect()->route('admin.suppliers.show', $supplier)->with('success', __('admin.suppliers.updated'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorizeShopOwnership($supplier->shop_id);

        if ($supplier->hasOpenPurchaseOrders()) {
            return back()->with('error', __('admin.suppliers.has_open_orders'));
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('success', __('admin.suppliers.deleted'));
    }
}
