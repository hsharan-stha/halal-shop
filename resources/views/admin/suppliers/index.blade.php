<x-layouts.admin :title="__('admin.nav.suppliers')">
    <x-ui.page-header :title="__('admin.suppliers.title')" :description="__('admin.suppliers.description')">
        <x-slot:actions>
            @can('suppliers.manage')
                <x-ui.button :href="route('admin.suppliers.create')" icon="plus">{{ __('admin.suppliers.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <label for="supplier-q" class="sr-only">{{ __('admin.search') }}</label>
        <input id="supplier-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.suppliers.search_placeholder') }}" class="form-control max-w-sm flex-1">
        <label for="supplier-status" class="sr-only">{{ __('admin.status') }}</label>
        <select id="supplier-status" name="status" class="form-control w-auto pr-8">
            <option value="">{{ __('admin.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('admin.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>{{ __('admin.inactive') }}</option>
        </select>
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
    </form>

    @if ($suppliers->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building" :title="__('admin.suppliers.empty')">
                @can('suppliers.manage')
                    <x-ui.button :href="route('admin.suppliers.create')" icon="plus">{{ __('admin.suppliers.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.suppliers.name') }}</th>
                <th>{{ __('admin.suppliers.contact') }}</th>
                <th class="text-right">{{ __('admin.suppliers.products') }}</th>
                <th class="text-right">{{ __('admin.purchase_orders.title') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($suppliers as $supplier)
                <tr>
                    <td>
                        <a href="{{ route('admin.suppliers.show', $supplier) }}" class="font-medium hover:text-primary">{{ $supplier->name }}</a>
                        <p class="text-xs text-ink-muted">
                            @if ($supplier->code)<span class="font-mono">{{ $supplier->code }}</span> · @endif{{ $supplier->company_name ?: country_name($supplier->country_code) }}
                        </p>
                    </td>
                    <td class="text-sm">
                        <p>{{ $supplier->contact_name ?: '—' }}</p>
                        @if ($supplier->email || $supplier->phone)<p class="text-xs text-ink-muted">{{ $supplier->email ?: $supplier->phone }}</p>@endif
                    </td>
                    <td class="text-right tabular-nums">{{ number_format($supplier->supplier_products_count) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($supplier->purchase_orders_count) }}</td>
                    <td><x-ui.badge :color="$supplier->is_active ? 'success' : 'neutral'">{{ $supplier->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge></td>
                    <td class="text-right">@include('admin.suppliers._actions')</td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($suppliers as $supplier)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <a href="{{ route('admin.suppliers.show', $supplier) }}" class="min-w-0">
                            <p class="font-medium">{{ $supplier->name }}</p>
                            <p class="text-xs text-ink-muted">{{ trans_choice('admin.suppliers.product_count', $supplier->supplier_products_count, ['count' => $supplier->supplier_products_count]) }} · {{ $supplier->is_active ? __('admin.active') : __('admin.inactive') }}</p>
                        </a>
                        @include('admin.suppliers._actions')
                    </div>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $suppliers->links() }}</div>
    @endif
</x-layouts.admin>
