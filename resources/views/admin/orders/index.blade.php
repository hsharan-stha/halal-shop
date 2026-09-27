<x-layouts.admin :title="__('admin.nav.orders')">
    <x-ui.page-header :title="__('admin.orders.title')" :description="__('admin.orders.description')" />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="order-q" class="form-label">{{ __('admin.search') }}</label>
            <input id="order-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.orders.search_placeholder') }}" class="form-control">
        </div>
        <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\OrderStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('admin.all')" />
        <div class="flex items-end justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('admin.orders.index')">{{ __('admin.reset') }}</x-ui.button>
            <x-ui.button icon="filter">{{ __('admin.apply') }}</x-ui.button>
        </div>
    </form>

    @if ($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="receipt" :title="__('admin.orders.empty')" :description="__('admin.orders.empty_hint')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('shop.orders.number') }}</th>
                <th>{{ __('admin.orders.customer') }}</th>
                <th>{{ __('admin.orders.placed_at') }}</th>
                <th class="text-right">{{ __('admin.orders.total') }}</th>
                <th>{{ __('admin.orders.payment') }}</th>
                <th>{{ __('admin.status') }}</th>
            </x-slot:head>
            @foreach ($orders as $order)
                <tr>
                    <td>
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-sm font-medium hover:text-primary">{{ $order->order_number }}</a>
                        <p class="text-xs text-ink-muted">{{ trans_choice('shop.cart.count', $order->items_count, ['count' => $order->items_count]) }}</p>
                    </td>
                    <td class="text-sm">
                        <span class="block">{{ $order->customer?->name ?? __('admin.orders.deleted_customer') }}</span>
                        <span class="block text-xs text-ink-muted">{{ $order->customer?->email }}</span>
                    </td>
                    <td class="text-sm whitespace-nowrap">{{ local_date($order->placed_at, true) }}</td>
                    <td class="text-right tabular-nums">{{ money($order->total) }}</td>
                    <td><x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge></td>
                    <td><x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($orders as $order)
                    <a href="{{ route('admin.orders.show', $order) }}" class="block space-y-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium">{{ $order->order_number }}</p>
                                <p class="truncate text-xs text-ink-muted">{{ $order->customer?->name ?? __('admin.orders.deleted_customer') }}</p>
                            </div>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-xs">{{ money($order->total) }} · {{ $order->payment_status->label() }}</p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.admin>
