<x-layouts.shop :title="__('shop.search.title')" :description="__('shop.search.prompt')" noindex>
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.search.title') }}</h1>

        @if ($term === '')
            <form method="GET" action="{{ route('search') }}" role="search" class="mt-6">
                <label for="search-q" class="form-label">{{ __('shop.search.placeholder') }}</label>
                <div class="flex gap-2">
                    <input id="search-q" type="search" name="q" required maxlength="100" class="form-control" placeholder="{{ __('shop.search.placeholder') }}" autofocus enterkeyhint="search">
                    <x-ui.button icon="search">{{ __('shop.nav.search') }}</x-ui.button>
                </div>
            </form>
            <p class="mt-4 text-sm text-ink-muted">{{ __('shop.search.prompt') }}</p>
        @else
            <p class="mt-1 text-sm text-ink-muted">{{ __('shop.search.for', ['term' => $term]) }}</p>

            @if ($categories->isNotEmpty() || $brands->isNotEmpty())
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @if ($categories->isNotEmpty())
                        <section class="card p-4">
                            <h2 class="mb-2 text-sm font-semibold">{{ __('shop.search.categories') }}</h2>
                            <ul class="space-y-1 text-sm">
                                @foreach ($categories as $category)
                                    <li><a href="{{ route('categories.show', $category) }}" class="hover:text-primary">{{ $category->localizedName() }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    @if ($brands->isNotEmpty())
                        <section class="card p-4">
                            <h2 class="mb-2 text-sm font-semibold">{{ __('shop.search.brands') }}</h2>
                            <ul class="space-y-1 text-sm">
                                @foreach ($brands as $brand)
                                    <li><a href="{{ route('brands.show', $brand) }}" class="hover:text-primary">{{ $brand->localizedName() }}</a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            @endif

            <h2 class="mt-8 mb-4 text-lg font-semibold">{{ __('shop.search.products') }}</h2>
            <div class="grid gap-6 lg:grid-cols-4">
                <aside class="lg:col-span-1">
                    <div class="card p-4">
                        <h2 class="mb-4 text-sm font-semibold">{{ __('shop.catalog.filters') }}</h2>
                        @include('shop.catalog._filters', ['action' => route('search'), 'locked' => []])
                    </div>
                </aside>
                <div class="min-w-0 lg:col-span-3">
                    @include('shop.catalog._grid')
                </div>
            </div>
        @endif
    </div>
</x-layouts.shop>
