@use('App\Enums\PurchaseOrderStatus')

<x-layouts.admin :title="$order->reference()">
    <x-ui.page-header :title="$order->reference()" :back="route('admin.purchase-orders.index')" :description="$order->supplier?->name">
        <x-slot:actions>
            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
            @can('purchase_orders.manage')
                @if ($order->status->isEditable())
                    <x-ui.button variant="secondary" size="sm" icon="pencil" :href="route('admin.purchase-orders.edit', $order)">{{ __('admin.edit') }}</x-ui.button>
                    <x-ui.confirm-form :action="route('admin.purchase-orders.order', $order)" :message="__('admin.purchase_orders.confirm_order')">
                        <x-ui.button size="sm" icon="check">{{ __('admin.purchase_orders.mark_ordered') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
                @if ($order->status->isReceivable())
                    @can('inventory.receive')
                        <x-ui.button size="sm" icon="download" :href="route('admin.purchase-orders.receive', $order)">{{ __('admin.purchase_orders.receive') }}</x-ui.button>
                    @endcan
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($order->isOverdue())
        <x-ui.alert type="warning" class="mb-6">{{ __('admin.purchase_orders.overdue_notice', ['date' => local_date($order->expected_at)]) }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.purchase_orders.lines')" padding="p-0">
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $line)
                        @php($progress = $line->quantity > 0 ? min(100, (int) round($line->quantity_received / $line->quantity * 100)) : 0)
                        <li class="space-y-2 px-4 py-4 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ $line->variant?->adminLabel() ?? '—' }}</p>
                                    <p class="text-xs text-ink-muted tabular-nums">{{ number_format($line->quantity) }} × {{ money($line->unit_cost) }}</p>
                                </div>
                                <p class="text-sm font-semibold tabular-nums">{{ money($line->lineTotal()) }}</p>
                            </div>
                            @if ($order->status !== PurchaseOrderStatus::Draft)
                                <div>
                                    <div class="flex justify-between text-xs text-ink-muted">
                                        <span>{{ __('admin.purchase_orders.received_progress', ['received' => number_format($line->quantity_received), 'ordered' => number_format($line->quantity)]) }}</span>
                                        <span class="tabular-nums">{{ $progress }}%</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"
                                         aria-label="{{ __('admin.purchase_orders.received_progress', ['received' => $line->quantity_received, 'ordered' => $line->quantity]) }}">
                                        <div @class(['h-full rounded-full', 'bg-success' => $progress >= 100, 'bg-primary' => $progress < 100]) style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                            @endif
                            @if ($line->batches->isNotEmpty())
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($line->batches as $batch)
                                        <li>
                                            <a href="{{ route('admin.batches.show', $batch) }}" class="inline-flex items-center gap-1 rounded-lg border border-line px-2 py-1 text-xs hover:border-primary">
                                                <x-icon name="layers" class="size-3.5" />
                                                <span class="font-mono">{{ $batch->batch_number }}</span>
                                                <span class="text-ink-muted">· {{ number_format($batch->initial_quantity) }} · {{ $batch->expires_at ? local_date($batch->expires_at) : '—' }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <div class="flex justify-between border-t border-line px-4 py-3 text-sm sm:px-6">
                    <span class="font-medium">{{ __('admin.purchase_orders.subtotal') }}</span>
                    <span class="font-semibold tabular-nums">{{ money($order->subtotal) }}</span>
                </div>
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.purchase_orders.details')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-ink-muted">{{ __('admin.purchase_orders.supplier') }}</dt>
                        <dd>
                            @if ($order->supplier && ! $order->supplier->trashed())
                                <a href="{{ route('admin.suppliers.show', $order->supplier) }}" class="text-primary hover:underline">{{ $order->supplier->name }}</a>
                            @else
                                {{ $order->supplier?->name ?? '—' }}
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.expected_at') }}</dt><dd>{{ $order->expected_at ? local_date($order->expected_at) : '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.created_at') }}</dt><dd>{{ local_date($order->created_at, true) }} @if ($order->creator)· {{ $order->creator->name }}@endif</dd></div>
                    @if ($order->ordered_at)
                        <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.ordered_at') }}</dt><dd>{{ local_date($order->ordered_at, true) }}</dd></div>
                    @endif
                    @if ($order->received_at)
                        <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.received_at') }}</dt><dd>{{ local_date($order->received_at, true) }}</dd></div>
                    @endif
                    @if ($order->cancelled_at)
                        <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.cancelled_at') }}</dt><dd>{{ local_date($order->cancelled_at, true) }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            @if ($order->notes)
                <x-ui.card :title="__('admin.purchase_orders.notes')">
                    <p class="text-sm whitespace-pre-line">{{ $order->notes }}</p>
                </x-ui.card>
            @endif

            @can('purchase_orders.manage')
                @if ($order->status->isCancellable())
                    <x-ui.card :title="__('admin.purchase_orders.cancel')">
                        <x-ui.confirm-form :action="route('admin.purchase-orders.cancel', $order)" :message="__('admin.purchase_orders.confirm_cancel')" class="space-y-3">
                            <x-ui.input name="reason" id="cancel-reason" :label="__('admin.inventory.reason')" maxlength="250" />
                            <x-ui.button variant="danger" icon="x" class="w-full">{{ __('admin.purchase_orders.cancel') }}</x-ui.button>
                        </x-ui.confirm-form>
                    </x-ui.card>
                @endif
                @if ($order->status === PurchaseOrderStatus::Draft)
                    <x-ui.confirm-form :action="route('admin.purchase-orders.destroy', $order)" method="DELETE" :message="__('admin.purchase_orders.confirm_delete')">
                        <x-ui.button variant="ghost" icon="trash" class="w-full text-danger">{{ __('admin.purchase_orders.delete_draft') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.admin>
