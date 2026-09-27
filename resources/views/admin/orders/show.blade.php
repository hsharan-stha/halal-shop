<x-layouts.admin :title="$order->order_number">
    <x-ui.page-header :title="$order->order_number" :back="route('admin.orders.index')" :description="local_date($order->placed_at, true)">
        <x-slot:actions>
            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
            <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
            @can('orders.update')
                @if ($order->payment_status->value === 'unpaid' && $order->status->value !== 'cancelled')
                    <x-ui.confirm-form :action="route('admin.orders.pay', $order)" :message="__('admin.orders.confirm_paid')">
                        <x-ui.button size="sm">{{ __('admin.orders.mark_paid') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
                @if ($order->status->isOpen() && ($order->payment_method->value === 'cash_on_delivery' || $order->payment_status->value === 'paid'))
                    <x-ui.confirm-form :action="route('admin.orders.ship', $order)" :message="__('admin.orders.confirm_ship')">
                        <x-ui.button size="sm" variant="secondary">{{ __('admin.orders.ship') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
                @if ($order->status->value === 'shipped')
                    <x-ui.confirm-form :action="route('admin.orders.complete', $order)" :message="__('admin.orders.confirm_complete')">
                        <x-ui.button size="sm" variant="secondary">{{ __('admin.orders.complete') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
            @endcan
            @can('orders.cancel')
                @if ($order->status->isOpen())
                    <x-ui.confirm-form :action="route('admin.orders.cancel', $order)" :message="__('admin.orders.confirm_cancel')">
                        <x-ui.button size="sm" variant="secondary">{{ __('admin.orders.cancel') }}</x-ui.button>
                    </x-ui.confirm-form>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            <x-ui.card :title="__('admin.orders.items')" padding="p-0">
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-6">
                            <div class="min-w-0">
                                <p class="text-sm font-medium">{{ $item->product_name }}</p>
                                <p class="text-xs text-ink-muted">{{ $item->variant_label }} · {{ $item->sku }}</p>
                                <p class="text-xs tabular-nums text-ink-muted">{{ $item->quantity }} × {{ money($item->unit_price) }}</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums">{{ money($item->line_total) }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.halal_shops.name')">
                <p class="font-medium">{{ $order->shop?->name ?? '—' }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ $order->delivery_to?->label() }}</p>
                @if ($order->pickupShop)
                    <p class="text-sm">{{ $order->pickupShop->name }}</p>
                @endif
                <p class="mt-2 text-sm">{{ __('admin.halal_shops.commission_amount') }} {{ money($order->commission_amount) }}</p>
            </x-ui.card>
            <x-ui.card :title="__('admin.orders.customer')">
                <p class="font-medium">{{ $order->customer?->name ?? __('admin.orders.deleted_customer') }}</p>
                <p class="text-sm text-ink-muted">{{ $order->customer?->email }}</p>
            </x-ui.card>
            <x-ui.card :title="__('admin.orders.address')">
                <p class="font-medium">{{ $order->recipient_name }}</p>
                <p class="text-sm">{{ $order->addressSummary() }}</p>
                <p class="text-sm text-ink-muted">{{ $order->phone }}</p>
                @if ($order->customer_note)
                    <p class="mt-3 text-sm whitespace-pre-line"><span class="font-medium">{{ __('admin.orders.note') }}</span><br>{{ $order->customer_note }}</p>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('admin.orders.payment')">
                <p class="text-sm">{{ $order->payment_method->label() }}</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>{{ __('admin.orders.items_total') }}</dt><dd class="tabular-nums">{{ money($order->items_total) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>{{ __('admin.orders.tax') }}</dt><dd class="tabular-nums">{{ money($order->tax_total) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>{{ __('admin.orders.shipping') }}</dt><dd class="tabular-nums">{{ money($order->shipping_total) }}</dd></div>
                    @if ($order->cod_fee > 0)
                        <div class="flex justify-between gap-4"><dt>{{ __('admin.orders.cod_fee') }}</dt><dd class="tabular-nums">{{ money($order->cod_fee) }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-line pt-2 font-semibold"><dt>{{ __('admin.orders.total') }}</dt><dd class="tabular-nums">{{ money($order->total) }}</dd></div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
