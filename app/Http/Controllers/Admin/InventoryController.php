<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BatchStatus;
use App\Enums\StockStatus;
use App\Exceptions\Inventory\InventoryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceiveStockRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:inventory.view', only: ['index', 'show']),
            new Middleware('can:inventory.receive', only: ['receiveForm', 'receive']),
            new Middleware('can:inventory.adjust', only: ['adjust', 'update']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', Rule::enum(StockStatus::class)],
        ]);

        $items = InventoryItem::query()
            ->withSellableQuantity()
            ->addSelect(['next_expiry' => InventoryBatch::query()->selectRaw('min(expires_at)')->whereColumn('inventory_batches.inventory_item_id', 'inventory_items.id')->inStock()])
            ->with(['variant' => fn ($query) => $query->select(['id', 'product_id', 'sku', 'name', 'is_active', 'deleted_at'])->with('product:id,name,japanese_name,sku,status,storage_type,deleted_at')])
            ->whereHas('variant.product')
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->whereHas('variant', fn (Builder $variants) => $variants
                ->where('sku', 'like', '%'.$q.'%')
                ->orWhere('barcode', 'like', '%'.$q.'%')
                ->orWhereHas('product', fn (Builder $products) => $products->where('name', 'like', '%'.$q.'%')->orWhere('japanese_name', 'like', '%'.$q.'%'))))
            ->when($filters['stock'] ?? null, fn (Builder $query, string $stock) => $query->whereStockStatus(StockStatus::from($stock)))
            ->orderBy('sellable_quantity')
            ->orderBy('inventory_items.id')
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        $warningDays = $this->warningDays();

        $summary = [
            'units_on_hand' => (int) InventoryItem::query()->sum('quantity_on_hand'),
            'low_stock' => InventoryItem::query()->forSellableCatalog()->whereStockStatus(StockStatus::LowStock)->count(),
            'out_of_stock' => InventoryItem::query()->forSellableCatalog()->whereStockStatus(StockStatus::OutOfStock)->count(),
            'expiring' => InventoryBatch::query()->expiringWithin($warningDays)->count(),
            'expired' => InventoryBatch::query()->expired()->count(),
        ];

        return view('admin.inventory.index', compact('items', 'filters', 'summary', 'warningDays'));
    }

    public function show(Request $request, InventoryItem $item): View
    {
        $showDisposed = $request->boolean('disposed');
        $item->load(['variant.product:id,name,japanese_name,sku,storage_type,deleted_at']);

        $batches = $item->batches()
            ->with('supplier:id,name,deleted_at')
            ->when(! $showDisposed, fn (Builder $query) => $query->where('status', '!=', BatchStatus::Disposed))
            ->allocationOrder()
            ->get();

        $movements = $item->movements()
            ->with(['user:id,name', 'batch:id,batch_number', 'reference'])
            ->latest('id')
            ->paginate(20, pageName: 'movements')
            ->withQueryString();

        return view('admin.inventory.show', [
            'item' => $item,
            'batches' => $batches,
            'movements' => $movements,
            'sellable' => $item->sellableQuantity(),
            'showDisposed' => $showDisposed,
            'disposedCount' => $showDisposed ? 0 : $item->batches()->where('status', BatchStatus::Disposed)->count(),
        ]);
    }

    public function receiveForm(InventoryItem $item): View
    {
        $item->load(['variant.product:id,name,japanese_name,sku,storage_type,deleted_at', 'variant.supplierProducts']);
        $preferred = $item->variant?->supplierProducts->sortByDesc('is_preferred')->first();

        return view('admin.inventory.receive', [
            'item' => $item,
            'suppliers' => Supplier::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'defaults' => ['supplier_id' => $preferred?->supplier_id, 'unit_cost' => $preferred?->unit_cost ?? $item->variant?->cost_price],
        ]);
    }

    public function receive(ReceiveStockRequest $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        try {
            $inventory->receive($item, (int) $request->validated('quantity'), $request->batchAttributes(), null, $request->user(), $request->validated('reason'));
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.inventory.show', $item)->with('success', __('admin.inventory.received', ['quantity' => $request->validated('quantity')]));
    }

    /**
     * Stock correction for items without batch tracking. Batch-tracked items
     * are adjusted per batch from the batch screen.
     */
    public function adjust(Request $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:250'],
        ]);

        try {
            $inventory->adjust($item, (int) $data['delta'], $data['reason'], null, $request->user());
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('admin.inventory.adjusted'));
    }

    public function update(Request $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'location' => ['nullable', 'string', 'max:60'],
            'track_batches' => ['boolean'],
        ]);

        try {
            $inventory->setBatchTracking($item, $request->boolean('track_batches'));
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        $item->fill(['low_stock_threshold' => $data['low_stock_threshold'] ?? null, 'location' => $data['location'] ?? null])->save();

        return back()->with('success', __('admin.inventory.settings_saved'));
    }

    private function warningDays(): int
    {
        return (int) (collect(settings('inventory.expiry_warning_days'))->max() ?? 30);
    }
}
