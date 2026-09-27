<x-layouts.shop :title="settings()->translated('seo.meta_title') ?: null" :description="settings()->translated('seo.meta_description') ?: __('shop.home.tagline')">
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <section class="rounded-3xl bg-primary px-6 py-10 text-on-primary sm:px-10 sm:py-14">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $branding->name() }}</h1>
            <p class="mt-3 max-w-xl text-sm opacity-90 sm:text-base">{{ settings()->translated('seo.meta_description') ?: __('shop.home.tagline') }}</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <a href="{{ route('shop.index') }}" class="btn bg-surface text-ink hover:bg-surface-muted">{{ __('shop.home.shop_now') }}</a>
                @if ($categories->isNotEmpty())
                    <a href="{{ route('categories.index') }}" class="btn border border-white/40 text-on-primary hover:bg-white/10">{{ __('shop.home.categories') }}</a>
                @endif
            </div>
        </section>

        @if ($categories->isNotEmpty())
            <section class="mt-8" aria-labelledby="home-categories">
                <div class="mb-3 flex items-end justify-between">
                    <h2 id="home-categories" class="text-xl font-bold">{{ __('shop.home.categories') }}</h2>
                    <a href="{{ route('categories.index') }}" class="text-sm font-medium text-primary hover:underline">{{ __('shop.view_all') }}</a>
                </div>
                <ul class="flex gap-2 overflow-x-auto pb-1">
                    @foreach ($categories as $category)
                        <li class="shrink-0">
                            <a href="{{ route('categories.show', $category) }}" class="card inline-flex items-center gap-2 px-4 py-3 text-sm font-medium hover:border-primary">
                                <x-icon :name="$category->icon ?: 'squares'" class="size-5 text-primary" />
                                {{ $category->localizedName() }}
                                <span class="text-xs text-ink-muted tabular-nums">{{ number_format($category->products_count) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-shop.product-section :title="__('shop.home.featured')" :products="$featured" :href="route('shop.index')" :wishlist-ids="$wishlistIds" />
        <x-shop.product-section :title="__('shop.home.certified')" :products="$certified" :href="route('shop.index', ['halal' => 'certified'])" :wishlist-ids="$wishlistIds" />
        <x-shop.product-section :title="__('shop.home.frozen')" :products="$frozen" :href="route('shop.index', ['storage' => 'frozen'])" :wishlist-ids="$wishlistIds" />
        <x-shop.product-section :title="__('shop.home.new_arrivals')" :products="$arrivals" :href="route('shop.index')" :wishlist-ids="$wishlistIds" />

        @if ($brands->isNotEmpty())
            <section class="mt-10">
                <h2 class="mb-4 text-xl font-bold">{{ __('shop.home.brands') }}</h2>
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($brands as $brand)
                        <li>
                            <a href="{{ route('brands.show', $brand) }}" class="card flex h-full flex-col items-center justify-center gap-2 p-4 text-center text-sm font-medium hover:border-primary">
                                @if ($brand->logoUrl())
                                    <img src="{{ $brand->logoUrl() }}" alt="" class="h-10 w-auto max-w-full object-contain" loading="lazy">
                                @endif
                                {{ $brand->localizedName() }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <x-shop.product-section :title="__('shop.home.recent')" :products="$recent" :wishlist-ids="$wishlistIds" />

        @if ($featured->isEmpty() && $arrivals->isEmpty())
            <x-ui.empty-state class="mt-10" icon="cube" :title="__('shop.home.empty')" />
        @endif
    </div>
</x-layouts.shop>
