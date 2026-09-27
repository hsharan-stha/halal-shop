<x-layouts.admin :title="__('admin.nav.categories')">
    <x-ui.page-header :title="__('admin.categories.title')" :description="__('admin.categories.description')">
        <x-slot:actions>
            @can('categories.manage')
                <x-ui.button :href="route('admin.categories.create')" icon="plus">{{ __('admin.categories.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex gap-2">
        <label for="category-q" class="sr-only">{{ __('admin.search') }}</label>
        <input id="category-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('admin.search_placeholder') }}" class="form-control max-w-sm">
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
    </form>

    @if ($rows->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="squares" :title="__('admin.categories.empty')">
                @can('categories.manage')
                    <x-ui.button :href="route('admin.categories.create')" icon="plus">{{ __('admin.categories.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.categories.name') }}</th>
                <th>{{ __('admin.slug') }}</th>
                <th class="text-right">{{ __('admin.products.title') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($rows as ['category' => $category, 'depth' => $depth])
                <tr>
                    <td>
                        <div class="flex items-center gap-3" style="padding-inline-start: {{ $depth * 1.25 }}rem">
                            @if ($depth > 0)<span class="text-ink-muted" aria-hidden="true">└</span>@endif
                            @if ($category->image_path)
                                <img src="{{ $category->imageUrl() }}" alt="" class="size-9 rounded-lg border border-line object-cover" loading="lazy">
                            @else
                                <span class="grid size-9 place-items-center rounded-lg bg-surface-muted text-ink-muted"><x-icon :name="$category->icon ?: 'squares'" class="size-5" /></span>
                            @endif
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-medium">{{ $category->localizedName() }} <x-admin.owner-badge :row="$category" /></p>
                                @if ($search !== '' && $category->parent)
                                    <p class="text-xs text-ink-muted">{{ $category->parent->localizedName() }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="font-mono text-xs text-ink-muted">{{ $category->slug }}</td>
                    <td class="text-right tabular-nums">{{ number_format($category->products_count) }}</td>
                    <td><x-ui.badge :color="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge></td>
                    <td class="text-right">@include('admin.categories._actions')</td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($rows as ['category' => $category, 'depth' => $depth])
                    <div class="flex items-start justify-between gap-3 p-4" style="padding-inline-start: {{ 1 + $depth * 1 }}rem">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 font-medium">@if ($depth > 0)<span class="text-ink-muted" aria-hidden="true">└ </span>@endif{{ $category->localizedName() }} <x-admin.owner-badge :row="$category" /></p>
                            <p class="text-xs text-ink-muted">{{ trans_choice('admin.categories.product_count', $category->products_count, ['count' => $category->products_count]) }} · {{ $category->is_active ? __('admin.active') : __('admin.inactive') }}</p>
                        </div>
                        @include('admin.categories._actions')
                    </div>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        @if ($paginator)<div class="mt-4">{{ $paginator->links() }}</div>@endif
    @endif
</x-layouts.admin>
