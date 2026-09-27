<x-layouts.shop :title="__('shop.wishlist.title')" :description="__('shop.wishlist.empty_hint')" noindex>
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.wishlist.title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ trans_choice('shop.wishlist.count', $products->count(), ['count' => $products->count()]) }}</p>

        @if ($products->isEmpty())
            <x-ui.empty-state class="mt-8" icon="heart" :title="__('shop.wishlist.empty')" :description="__('shop.wishlist.empty_hint')">
                <x-ui.button :href="route('shop.index')">{{ __('shop.home.shop_now') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <li><x-shop.product-card :product="$product" :wished="true" /></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.shop>
