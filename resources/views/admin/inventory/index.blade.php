<x-layouts.admin :title="__('admin.nav.inventory')">
    <x-ui.page-header :title="__('admin.inventory.title')" :description="__('admin.inventory.description')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="layers" :href="route('admin.batches.index')">{{ __('admin.nav.batches') }}</x-ui.button>
            @can('purchase_orders.view')
                <x-ui.button variant="secondary" icon="clipboard" :href="route('admin.purchase-orders.index')">{{ __('admin.nav.purchase_orders') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ([
            ['key' => 'units_on_hand', 'tone' => null, 'href' => route('admin.inventory.index')],
            ['key' => 'low_stock', 'tone' => 'warning', 'href' => route('admin.inventory.index', ['stock' => 'low_stock'])],
            ['key' => 'out_of_stock', 'tone' => 'danger', 'href' => route('admin.inventory.index', ['stock' => 'out_of_stock'])],
            ['key' => 'expiring', 'tone' => 'warning', 'href' => route('admin.batches.index', ['expiry' => 'expiring'])],
            ['key' => 'expired', 'tone' => 'danger', 'href' => route('admin.batches.index', ['expiry' => 'expired'])],
        ] as $card)
            <a href="{{ $card['href'] }}" class="card block p-4 transition hover:border-primary">
                <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.inventory.summary.'.$card['key'], ['days' => $warningDays]) }}</p>
                <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-'.$card['tone'] => $card['tone'] && $summary[$card['key']] > 0])>{{ number_format($summary[$card['key']]) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <div class="w-full sm:w-auto sm:max-w-sm sm:flex-1">
            <label for="inventory-q" class="sr-only">{{ __('admin.search') }}</label>
            <input id="inventory-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.inventory.search_placeholder') }}" class="form-control">
        </div>
        <label for="inventory-stock" class="sr-only">{{ __('admin.inventory.stock_status') }}</label>
        <select id="inventory-stock" name="stock" class="form-control w-auto">
            <option value="">{{ __('admin.inventory.all_stock') }}</option>
            @foreach (\App\Enums\StockStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['stock'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
        @if (array_filter($filters))
            <x-ui.button variant="ghost" :href="route('admin.inventory.index')">{{ __('admin.reset') }}</x-ui.button>
        @endif
    </form>

    @if ($items->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="archive" :title="__('admin.inventory.empty')" :description="__('admin.inventory.empty_hint')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.products.product') }}</th>
                <th class="text-right">{{ __('admin.inventory.on_hand') }}</th>
                <th class="text-right">{{ __('admin.inventory.sellable') }}</th>
                <th>{{ __('admin.inventory.next_expiry') }}</th>
                <th>{{ __('admin.inventory.stock_status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($items as $item)
                @php($status = $item->stockStatus())
                <tr>
                    <td>
                        <a href="{{ route('admin.inventory.show', $item) }}" class="font-medium hover:text-primary">{{ $item->variant?->product?->localizedName() ?? '—' }}</a>
                        <p class="text-xs text-ink-muted">
                            @if ($item->variant?->translate('name')){{ $item->variant->translate('name') }} · @endif<span class="font-mono">{{ $item->variant?->sku }}</span>
                            @unless ($item->track_batches) · {{ __('admin.inventory.untracked') }}@endunless
                        </p>
                    </td>
                    <td class="text-right tabular-nums">{{ number_format($item->quantity_on_hand) }}</td>
                    <td class="text-right font-semibold tabular-nums">{{ number_format($item->sellableQuantity()) }}</td>
                    <td class="text-sm whitespace-nowrap">@include('admin.batches._expiry', ['date' => $item->next_expiry])</td>
                    <td><x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge></td>
                    <td class="text-right">
                        <div class="inline-flex items-center gap-1">
                            @can('inventory.receive')
                                <x-ui.button variant="ghost" size="sm" icon="plus" :href="route('admin.inventory.receive', $item)">{{ __('admin.inventory.receive') }}</x-ui.button>
                            @endcan
                            <x-ui.button variant="ghost" size="sm" icon="eye" :href="route('admin.inventory.show', $item)" :aria-label="__('admin.view')" />
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($items as $item)
                    @php($status = $item->stockStatus())
                    <a href="{{ route('admin.inventory.show', $item) }}" class="block space-y-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $item->variant?->product?->localizedName() ?? '—' }}</p>
                                <p class="text-xs text-ink-muted">@if ($item->variant?->translate('name')){{ $item->variant->translate('name') }} · @endif<span class="font-mono">{{ $item->variant?->sku }}</span></p>
                            </div>
                            <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-xs text-ink-muted">
                            {{ __('admin.inventory.sellable') }} <span class="font-semibold text-ink tabular-nums">{{ number_format($item->sellableQuantity()) }}</span>
                            · {{ __('admin.inventory.on_hand') }} {{ number_format($item->quantity_on_hand) }}
                            @if ($item->next_expiry) · @include('admin.batches._expiry', ['date' => $item->next_expiry]) @endif
                        </p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-layouts.admin>
