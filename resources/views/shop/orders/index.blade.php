<x-layouts.account :title="__('shop.orders.title')">
    <h1 class="text-2xl font-bold tracking-tight">{{ __('shop.orders.title') }}</h1>

    @if ($orders->isEmpty())
        <x-ui.empty-state class="mt-8" icon="receipt" :title="__('shop.orders.empty')" :description="__('shop.orders.empty_hint')">
            <x-ui.button :href="route('shop.index')">{{ __('shop.home.shop_now') }}</x-ui.button>
        </x-ui.empty-state>
    @else
        <ul class="mt-6 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('account.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-surface-muted">
                        <span class="min-w-0">
                            <span class="block font-mono text-sm font-medium">{{ $order->order_number }}</span>
                            <span class="block text-xs text-ink-muted">{{ local_date($order->placed_at, true) }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                            <span class="text-sm font-semibold tabular-nums">{{ money($order->total) }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.account>
