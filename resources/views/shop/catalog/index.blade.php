<x-layouts.shop :title="$title" :description="$description" :canonical="$action" :noindex="collect($filters)->filter(fn ($value, $key) => $key !== 'sort' && filled($value))->isNotEmpty()">
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <nav class="mb-3 text-sm text-ink-muted" aria-label="{{ __('shop.breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('shop.nav.home') }}</a>
            @isset($category)
                @if ($category->parent?->is_active)
                    <span aria-hidden="true"> / </span>
                    <a href="{{ route('categories.show', $category->parent) }}" class="hover:text-ink">{{ $category->parent->localizedName() }}</a>
                @endif
            @endisset
            <span aria-hidden="true"> / </span>
            <span class="text-ink">{{ $title }}</span>
        </nav>

        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                @isset($brand)
                    @if ($brand->logoUrl())
                        <img src="{{ $brand->logoUrl() }}" alt="" class="mb-2 h-12 w-auto object-contain">
                    @endif
                @endisset
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-1 max-w-2xl text-sm text-ink-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($category)
                @if ($category->children->isNotEmpty())
                    <ul class="flex gap-2 overflow-x-auto">
                        @foreach ($category->children as $child)
                            <li><a href="{{ route('categories.show', $child) }}" class="btn btn-secondary btn-sm whitespace-nowrap">{{ $child->localizedName() }}</a></li>
                        @endforeach
                    </ul>
                @endif
            @endisset
        </div>

        <div class="grid gap-6 lg:grid-cols-4">
            <aside class="lg:col-span-1">
                <div class="card p-4">
                    <h2 class="mb-4 text-sm font-semibold">{{ __('shop.catalog.filters') }}</h2>
                    @include('shop.catalog._filters')
                </div>
            </aside>

            <div class="min-w-0 lg:col-span-3">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-ink-muted">{{ trans_choice('shop.catalog.results', $products->total(), ['count' => number_format($products->total())]) }}</p>
                    <div>
                        <label for="catalog-sort" class="sr-only">{{ __('shop.catalog.sort') }}</label>
                        <select id="catalog-sort" name="sort" form="catalog-filters" class="form-control w-auto pr-8" onchange="this.form.requestSubmit()">
                            @foreach (['newest', 'price_asc', 'price_desc', 'name'] as $sort)
                                <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ __('shop.catalog.sorts.'.$sort) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @include('shop.catalog._grid')
            </div>
        </div>
    </div>
</x-layouts.shop>
