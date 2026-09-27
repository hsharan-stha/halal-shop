<x-layouts.admin :title="__('admin.nav.products')">
    <x-ui.page-header :title="__('admin.products.title')" :description="__('admin.products.description')">
        <x-slot:actions>
            @can('products.create')
                <x-ui.button :href="route('admin.products.create')" icon="plus">{{ __('admin.products.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="sm:col-span-2">
            <label for="product-q" class="form-label">{{ __('admin.search') }}</label>
            <input id="product-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.products.search_placeholder') }}" class="form-control">
        </div>
        <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\ProductStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('admin.all')" />
        <x-ui.select name="halal" :label="__('admin.halal.status')" :options="\App\Enums\HalalStatus::options()" :value="$filters['halal'] ?? null" :placeholder="__('admin.all')" />
        <x-ui.select name="category" :label="__('admin.categories.title')" :options="$categories" :value="$filters['category'] ?? null" :placeholder="__('admin.all')" />
        <x-ui.select name="label" :label="__('admin.food_label.title')" :options="['reviewed' => __('admin.food_label.reviewed'), 'unreviewed' => __('admin.food_label.unreviewed')]" :value="$filters['label'] ?? null" :placeholder="__('admin.all')" />
        <div class="flex flex-wrap items-end justify-between gap-3 sm:col-span-2 lg:col-span-6">
            <x-ui.checkbox name="trashed" :label="__('admin.products.show_trashed')" :checked="(bool) ($filters['trashed'] ?? false)" />
            <div class="flex gap-2">
                <x-ui.button variant="secondary" :href="route('admin.products.index')">{{ __('admin.reset') }}</x-ui.button>
                <x-ui.button icon="filter">{{ __('admin.apply') }}</x-ui.button>
            </div>
        </div>
    </form>

    @if ($products->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="cube" :title="__('admin.products.empty')" :description="__('admin.products.empty_hint')">
                @can('products.create')
                    <x-ui.button :href="route('admin.products.create')" icon="plus">{{ __('admin.products.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <div x-data="{ selected: [], all: @js($products->pluck('id')->all()) }">
            @canany(['products.update', 'products.delete'])
                <x-ui.confirm-form :action="route('admin.products.bulk')" :message="__('admin.products.bulk_confirm')" x-show="selected.length > 0" x-cloak
                                   class="sticky top-16 z-10 mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-primary/30 bg-primary-soft p-3">
                    <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <span class="text-sm font-medium" x-text="@js(__('admin.products.selected')).replace(':count', selected.length)"></span>
                    <label for="bulk-action" class="sr-only">{{ __('admin.products.bulk_action') }}</label>
                    <select id="bulk-action" name="action" class="form-control w-auto" required>
                        @can('products.update')
                            <option value="activate">{{ __('admin.products.bulk.activate') }}</option>
                            <option value="draft">{{ __('admin.products.bulk.draft') }}</option>
                            <option value="archive">{{ __('admin.products.bulk.archive') }}</option>
                        @endcan
                        @can('products.delete')
                            <option value="delete">{{ __('admin.products.bulk.delete') }}</option>
                        @endcan
                    </select>
                    <x-ui.button size="sm">{{ __('admin.apply') }}</x-ui.button>
                    <x-ui.button type="button" variant="ghost" size="sm" @click="selected = []">{{ __('shop.cancel') }}</x-ui.button>
                </x-ui.confirm-form>
            @endcanany

            <x-ui.table>
                <x-slot:head>
                    <th class="w-10">
                        <input type="checkbox" class="form-check" aria-label="{{ __('admin.products.select_all') }}"
                               :checked="selected.length === all.length" @change="selected = $event.target.checked ? [...all] : []">
                    </th>
                    <th>{{ __('admin.products.product') }}</th>
                    <th>{{ __('admin.products.price') }}</th>
                    <th>{{ __('admin.halal.status') }}</th>
                    <th>{{ __('admin.food_label.title') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th class="text-right">{{ __('admin.actions') }}</th>
                </x-slot:head>
                @foreach ($products as $product)
                    <tr>
                        <td><input type="checkbox" class="form-check" value="{{ $product->id }}" x-model.number="selected" aria-label="{{ __('admin.products.select', ['name' => $product->localizedName()]) }}"></td>
                        <td>
                            <div class="flex items-center gap-3">
                                @include('admin.products._thumb')
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $product->localizedName() }}</p>
                                    <p class="text-xs text-ink-muted"><span class="font-mono">{{ $product->sku }}</span> · {{ $product->category?->localizedName() }}@if ($product->brand) · {{ $product->brand->localizedName() }}@endif</p>
                                </div>
                            </div>
                        </td>
                        <td class="tabular-nums whitespace-nowrap">@include('admin.products._price')</td>
                        <td><x-admin.halal-status :product="$product" /></td>
                        <td>
                            <x-ui.badge :color="$product->hasReviewedFoodLabel() ? 'success' : 'warning'">{{ $product->hasReviewedFoodLabel() ? __('admin.food_label.reviewed') : __('admin.food_label.unreviewed') }}</x-ui.badge>
                        </td>
                        <td><x-ui.badge :color="$product->trashed() ? 'danger' : $product->status->color()">{{ $product->trashed() ? __('admin.products.trashed') : $product->status->label() }}</x-ui.badge></td>
                        <td class="text-right">@include('admin.products._actions')</td>
                    </tr>
                @endforeach
                <x-slot:mobile>
                    @foreach ($products as $product)
                        <div class="flex gap-3 p-4">
                            <input type="checkbox" class="form-check mt-1" value="{{ $product->id }}" x-model.number="selected" aria-label="{{ __('admin.products.select', ['name' => $product->localizedName()]) }}">
                            @include('admin.products._thumb')
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <p class="font-medium leading-snug">{{ $product->localizedName() }}</p>
                                <p class="text-xs text-ink-muted"><span class="font-mono">{{ $product->sku }}</span> · @include('admin.products._price')</p>
                                <div class="flex flex-wrap gap-1">
                                    <x-ui.badge :color="$product->trashed() ? 'danger' : $product->status->color()">{{ $product->trashed() ? __('admin.products.trashed') : $product->status->label() }}</x-ui.badge>
                                    <x-admin.halal-status :product="$product" />
                                </div>
                                @include('admin.products._actions')
                            </div>
                        </div>
                    @endforeach
                </x-slot:mobile>
            </x-ui.table>
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</x-layouts.admin>
