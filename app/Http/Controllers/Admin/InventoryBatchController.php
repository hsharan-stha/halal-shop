<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\StorageType;
use App\Exceptions\Inventory\InventoryException;
use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryBatchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:inventory.view', only: ['index', 'show']),
            new Middleware('can:inventory.adjust', only: ['adjust', 'dispose', 'transfer', 'status']),
        ];
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(BatchStatus::class)],
            'expiry' => ['nullable', 'in:expiring,expired'],
            'storage' => ['nullable', Rule::enum(StorageType::class)],
        ]);

        $warningDays = (int) (collect(settings('inventory.expiry_warning_days'))->max() ?? 30);

        $batches = InventoryBatch::query()
            ->with(['item.variant' => fn ($query) => $query->select(['id', 'product_id', 'sku', 'name', 'deleted_at'])->with('product:id,name,japanese_name,deleted_at')])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $inner) => $inner
                ->where('batch_number', 'like', '%'.$q.'%')
                ->orWhere('lot_number', 'like', '%'.$q.'%')
                ->orWhereHas('item.variant', fn (Builder $variants) => $variants->where('sku', 'like', '%'.$q.'%')
                    ->orWhereHas('product', fn (Builder $products) => $products->where('name', 'like', '%'.$q.'%')->orWhere('japanese_name', 'like', '%'.$q.'%')))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(! isset($filters['status']) && ! isset($filters['expiry']), fn (Builder $query) => $query->where('status', '!=', BatchStatus::Disposed))
            ->when(($filters['expiry'] ?? null) === 'expiring', fn (Builder $query) => $query->expiringWithin($warningDays))
            ->when(($filters['expiry'] ?? null) === 'expired', fn (Builder $query) => $query->expired())
            ->when($filters['storage'] ?? null, fn (Builder $query, string $storage) => $query->where('storage_type', $storage))
            ->allocationOrder()
            ->paginate(config('shop.pagination.admin'))
            ->withQueryString();

        $summary = [
            'available' => InventoryBatch::query()->allocatable()->count(),
            'quarantined' => InventoryBatch::query()->inStock()->where('status', BatchStatus::Quarantined)->count(),
            'expiring' => InventoryBatch::query()->expiringWithin($warningDays)->count(),
            'expired' => InventoryBatch::query()->expired()->count(),
        ];

        return view('admin.batches.index', compact('batches', 'filters', 'summary', 'warningDays'));
    }

    public function show(InventoryBatch $batch): View
    {
        $batch->load([
            'item.variant.product:id,name,japanese_name,sku,deleted_at',
            'supplier:id,name,deleted_at',
            'purchaseOrderItem.purchaseOrder:id,order_number',
        ]);

        return view('admin.batches.show', [
            'batch' => $batch,
            'movements' => $batch->movements()->with(['user:id,name', 'reference'])->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function adjust(Request $request, InventoryBatch $batch, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:250'],
        ]);

        return $this->attempt(fn () => $inventory->adjust($batch->item()->firstOrFail(), (int) $data['delta'], $data['reason'], $batch, $request->user()), 'admin.batches.adjusted');
    }

    public function dispose(Request $request, InventoryBatch $batch, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $batch->quantity)],
            'type' => ['required', Rule::in(array_map(fn (InventoryMovementType $type) => $type->value, InventoryMovementType::disposals()))],
            'reason' => ['required', 'string', 'max:250'],
        ]);

        return $this->attempt(fn () => $inventory->dispose($batch, (int) $data['quantity'], InventoryMovementType::from($data['type']), $data['reason'], $request->user()), 'admin.batches.disposed');
    }

    public function transfer(Request $request, InventoryBatch $batch, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $batch->quantity)],
            'location' => ['required', 'string', 'max:60', Rule::notIn([(string) $batch->location])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $destination = $inventory->transfer($batch, (int) $data['quantity'], $data['location'], $data['reason'] ?? null, $request->user());
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.batches.show', $destination)->with('success', __('admin.batches.transferred'));
    }

    public function status(Request $request, InventoryBatch $batch, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([BatchStatus::Available->value, BatchStatus::Quarantined->value, BatchStatus::Expired->value])],
            'reason' => [Rule::requiredIf($request->input('status') === BatchStatus::Quarantined->value), 'nullable', 'string', 'max:250'],
        ]);

        return $this->attempt(fn () => $inventory->changeBatchStatus($batch, BatchStatus::from($data['status']), $data['reason'] ?? null, $request->user()), 'admin.batches.status_changed');
    }

    private function attempt(callable $operation, string $successMessage): RedirectResponse
    {
        try {
            $operation();
        } catch (InventoryException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', __($successMessage));
    }
}
