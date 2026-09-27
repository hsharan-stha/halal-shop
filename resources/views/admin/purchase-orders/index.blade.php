<x-layouts.admin :title="__('admin.nav.purchase_orders')">
    <x-ui.page-header :title="__('admin.purchase_orders.title')" :description="__('admin.purchase_orders.description')">
        <x-slot:actions>
            @can('purchase_orders.manage')
                <x-ui.button :href="route('admin.purchase-orders.create')" icon="plus">{{ __('admin.purchase_orders.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="po-q" class="form-label">{{ __('admin.search') }}</label>
            <input id="po-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.purchase_orders.search_placeholder') }}" class="form-control">
        </div>
        <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\PurchaseOrderStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('admin.all')" />
        <x-ui.select name="supplier" :label="__('admin.purchase_orders.supplier')" :options="$suppliers" :value="$filters['supplier'] ?? null" :placeholder="__('admin.all')" />
        <div class="flex items-end justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('admin.purchase-orders.index')">{{ __('admin.reset') }}</x-ui.button>
            <x-ui.button icon="filter">{{ __('admin.apply') }}</x-ui.button>
        </div>
    </form>

    @if ($orders->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="clipboard" :title="__('admin.purchase_orders.empty')" :description="__('admin.purchase_orders.empty_hint')">
                @can('purchase_orders.manage')
                    <x-ui.button :href="route('admin.purchase-orders.create')" icon="plus">{{ __('admin.purchase_orders.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.purchase_orders.number') }}</th>
                <th>{{ __('admin.purchase_orders.supplier') }}</th>
                <th>{{ __('admin.purchase_orders.expected_at') }}</th>
                <th class="text-right">{{ __('admin.purchase_orders.subtotal') }}</th>
                <th>{{ __('admin.status') }}</th>
            </x-slot:head>
            @foreach ($orders as $order)
                <tr>
                    <td>
                        <a href="{{ route('admin.purchase-orders.show', $order) }}" class="font-mono text-sm font-medium hover:text-primary">{{ $order->reference() }}</a>
                        <x-admin.owner-badge :row="$order" :platform="false" />
                        <p class="text-xs text-ink-muted">{{ local_date($order->created_at) }} · {{ trans_choice('admin.purchase_orders.line_count', $order->items_count, ['count' => $order->items_count]) }}</p>
                    </td>
                    <td class="text-sm">{{ $order->supplier?->name ?? '—' }}</td>
                    <td @class(['text-sm whitespace-nowrap', 'font-medium text-danger' => $order->isOverdue()])>
                        {{ $order->expected_at ? local_date($order->expected_at) : '—' }}
                        @if ($order->isOverdue())<span class="block text-xs">{{ __('admin.purchase_orders.overdue') }}</span>@endif
                    </td>
                    <td class="text-right tabular-nums">{{ money($order->subtotal) }}</td>
                    <td><x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($orders as $order)
                    <a href="{{ route('admin.purchase-orders.show', $order) }}" class="block space-y-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-medium">{{ $order->reference() }}</p>
                                <p class="text-xs text-ink-muted">{{ $order->supplier?->name ?? '—' }}</p>
                            </div>
                            <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-xs">
                            {{ money($order->subtotal) }}
                            @if ($order->expected_at) · <span @class(['text-danger' => $order->isOverdue()])>{{ __('admin.purchase_orders.expected_at') }} {{ local_date($order->expected_at) }}</span>@endif
                        </p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.admin>
