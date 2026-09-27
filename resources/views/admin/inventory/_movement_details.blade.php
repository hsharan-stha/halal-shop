@if ($movement->reference instanceof \App\Models\PurchaseOrder)
    @can('purchase_orders.view')
        <a href="{{ route('admin.purchase-orders.show', $movement->reference) }}" class="font-mono text-primary hover:underline">{{ $movement->reference->reference() }}</a>
    @else
        <span class="font-mono">{{ $movement->reference->reference() }}</span>
    @endcan
@endif
@if ($movement->reason)
    <span class="block text-ink">{{ $movement->reason }}</span>
@endif
<span class="block text-xs text-ink-muted">{{ $movement->user?->name ?? __('admin.movements.system') }}</span>
