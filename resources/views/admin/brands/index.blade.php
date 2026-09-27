<x-layouts.admin :title="__('admin.nav.brands')">
    <x-ui.page-header :title="__('admin.brands.title')" :description="__('admin.brands.description')">
        <x-slot:actions>
            @can('brands.manage')
                <x-ui.button :href="route('admin.brands.create')" icon="plus">{{ __('admin.brands.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex gap-2">
        <label for="brand-q" class="sr-only">{{ __('admin.search') }}</label>
        <input id="brand-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('admin.search_placeholder') }}" class="form-control max-w-sm">
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
    </form>

    @if ($brands->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="tag" :title="__('admin.brands.empty')">
                @can('brands.manage')
                    <x-ui.button :href="route('admin.brands.create')" icon="plus">{{ __('admin.brands.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.brands.name') }}</th>
                <th>{{ __('admin.brands.country') }}</th>
                <th class="text-right">{{ __('admin.products.title') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($brands as $brand)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($brand->logo_path)
                                <img src="{{ $brand->logoUrl() }}" alt="" class="size-9 rounded-lg border border-line bg-white object-contain" loading="lazy">
                            @else
                                <span class="grid size-9 place-items-center rounded-lg bg-surface-muted font-semibold text-ink-muted">{{ mb_substr($brand->name, 0, 1) }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-medium">{{ $brand->localizedName() }} <x-admin.owner-badge :row="$brand" /></p>
                                <p class="font-mono text-xs text-ink-muted">{{ $brand->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm">{{ country_name($brand->country_of_origin) ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ number_format($brand->products_count) }}</td>
                    <td><x-ui.badge :color="$brand->is_active ? 'success' : 'neutral'">{{ $brand->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge></td>
                    <td class="text-right">@include('admin.brands._actions')</td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($brands as $brand)
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-medium">{{ $brand->localizedName() }} <x-admin.owner-badge :row="$brand" /></p>
                            <p class="text-xs text-ink-muted">{{ trans_choice('admin.categories.product_count', $brand->products_count, ['count' => $brand->products_count]) }} · {{ $brand->is_active ? __('admin.active') : __('admin.inactive') }}</p>
                        </div>
                        @include('admin.brands._actions')
                    </div>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $brands->links() }}</div>
    @endif
</x-layouts.admin>
