<x-layouts.shop :title="$shop->name">
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <a href="{{ route('halal-shops.index') }}" class="text-sm text-ink-muted">{{ __('shop.halal_shops.title') }}</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ $shop->name }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ $shop->summary() }}@if ($distance !== null) · {{ number_format($distance, 1) }} km @endif</p>
        <p class="mt-3">
            <a href="{{ route('shop.index', ['shop' => $shop->slug]) }}" class="text-sm font-medium text-primary">{{ __('shop.halal_shops.all_items') }}</a>
        </p>
        <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            @forelse ($products as $product)
                <x-shop.product-card :product="$product" :wished="in_array($product->id, $wishlistIds, true)" />
            @empty
                <p class="col-span-full text-sm text-ink-muted">{{ __('shop.catalog.empty') }}</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    </div>
</x-layouts.shop>
