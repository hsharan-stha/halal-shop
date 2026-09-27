<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseOrderRequest;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Rules\VariantInOwnShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierProductController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('can:suppliers.manage')];
    }

    /**
     * Add or update the terms on which this supplier provides a variant.
     */
    public function store(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at'), new VariantInOwnShop],
            'supplier_sku' => ['nullable', 'string', 'max:64'],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:'.PurchaseOrderRequest::MAX_UNIT_COST],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1', 'max:'.PurchaseOrderRequest::MAX_QUANTITY],
            'is_preferred' => ['boolean'],
        ]);

        DB::transaction(function () use ($supplier, $data, $request): void {
            if ($request->boolean('is_preferred')) {
                SupplierProduct::query()->where('product_variant_id', $data['product_variant_id'])->update(['is_preferred' => false]);
            }

            $supplier->supplierProducts()->updateOrCreate(
                ['product_variant_id' => $data['product_variant_id']],
                [...$data, 'is_preferred' => $request->boolean('is_preferred')],
            );
        });

        return back()->with('success', __('admin.suppliers.product_saved'));
    }

    public function destroy(Supplier $supplier, SupplierProduct $supplierProduct): RedirectResponse
    {
        $supplierProduct->delete();

        return back()->with('success', __('admin.suppliers.product_removed'));
    }
}
