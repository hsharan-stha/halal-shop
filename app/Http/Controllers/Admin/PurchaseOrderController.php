<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\Inventory\InventoryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseOrderRequest;
use App\Http\Requests\Admin\ReceivePurchaseOrderRequest;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:purchase_orders.view', only: ['index', 'show']),
            new Middleware('can:purchase_orders.manage', except: ['index', 'show', 'receiveForm']),
            new Middleware(['can:purchase_orders.manage', 'can:inventory.receive'], only: ['receiveForm']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
            'supplier' => ['nullable', 'integer'],
        ]);

        $orders = PurchaseOrder::query()
            ->with('supplier:id,name,deleted_at')
            ->withCount('items')
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('order_number', 'like', '%'.$q.'%'))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier'] ?? null, fn (Builder $query, int $supplier) => $query->where('supplier_id', $supplier))
            ->latest('id')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'suppliers' => Supplier::query()->withTrashed()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.purchase-orders.form', $this->formData(new PurchaseOrder(['supplier_id' => $request->integer('supplier') ?: null])));
    }

    public function store(PurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse
    {
        $order = $service->save(new PurchaseOrder, $request->attributesForModel(), $request->lines(), $request->user());

        return redirect()->route('admin.purchase-orders.show', $order)->with('success', __('admin.purchase_orders.created'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load([
            'supplier',
            'creator:id,name',
            'items.variant.product:id,name,japanese_name,deleted_at',
            'items.batches:id,purchase_order_item_id,batch_number,lot_number,initial_quantity,expires_at,received_at',
        ]);

        return view('admin.purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', __('admin.purchase_orders.not_editable'));
        }

        $purchaseOrder->load('items');

        return view('admin.purchase-orders.form', $this->formData($purchaseOrder));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if (! $purchaseOrder->status->isEditable()) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', __('admin.purchase_orders.not_editable'));
        }

        $service->save($purchaseOrder, $request->attributesForModel(), $request->lines(), $request->user());

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', __('admin.purchase_orders.updated'));
    }

    public function destroy(PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        return $this->attempt(function () use ($purchaseOrder, $service): RedirectResponse {
            $service->delete($purchaseOrder);

            return redirect()->route('admin.purchase-orders.index')->with('success', __('admin.purchase_orders.deleted'));
        });
    }

    public function order(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        return $this->attempt(function () use ($request, $purchaseOrder, $service): RedirectResponse {
            $service->markOrdered($purchaseOrder, $request->user());

            return back()->with('success', __('admin.purchase_orders.marked_ordered'));
        });
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);

        return $this->attempt(function () use ($request, $purchaseOrder, $service, $data): RedirectResponse {
            $service->cancel($purchaseOrder, $request->user(), $data['reason'] ?? null);

            return back()->with('success', __('admin.purchase_orders.cancelled'));
        });
    }

    public function receiveForm(PurchaseOrder $purchaseOrder): View|RedirectResponse
    {
        if (! $purchaseOrder->status->isReceivable()) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', __('admin.purchase_orders.not_receivable'));
        }

        $purchaseOrder->load(['supplier', 'items.variant.product:id,name,japanese_name,deleted_at', 'items.variant.inventoryItem']);

        return view('admin.purchase-orders.receive', ['order' => $purchaseOrder]);
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $units = $service->receive($purchaseOrder, $request->receipts(), $request->user());
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        } catch (LogicException) {
            return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('error', __('admin.purchase_orders.not_receivable'));
        }

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', __('admin.purchase_orders.received', ['count' => $units]));
    }

    /**
     * @param  callable(): RedirectResponse  $operation
     */
    private function attempt(callable $operation): RedirectResponse
    {
        try {
            return $operation();
        } catch (InventoryException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (LogicException) {
            return back()->with('error', __('admin.purchase_orders.invalid_state'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(PurchaseOrder $order): array
    {
        $lines = old('lines', $order->exists
            ? $order->items->map(fn ($item) => ['product_variant_id' => $item->product_variant_id, 'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost])->all()
            : []);

        return [
            'order' => $order,
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'variantOptions' => ProductVariant::selectOptions(),
            'supplierCosts' => SupplierProduct::query()->whereNotNull('unit_cost')->get(['supplier_id', 'product_variant_id', 'unit_cost'])
                ->groupBy('supplier_id')->map(fn ($rows) => $rows->pluck('unit_cost', 'product_variant_id'))->all(),
            'lines' => array_values($lines),
        ];
    }
}
