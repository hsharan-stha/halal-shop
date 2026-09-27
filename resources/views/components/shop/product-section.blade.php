@props(['title', 'products', 'href' => null, 'wishlistIds' => []])

@if ($products->isNotEmpty())
    <section class="mt-10">
        <div class="mb-4 flex items-end justify-between gap-3">
            <h2 class="text-xl font-bold">{{ $title }}</h2>
            @if ($href)
                <a href="{{ $href }}" class="shrink-0 text-sm font-medium text-primary hover:underline">{{ __('shop.view_all') }}</a>
            @endif
        </div>
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($products as $product)
                <li><x-shop.product-card :product="$product" :wished="in_array($product->id, $wishlistIds, true)" /></li>
            @endforeach
        </ul>
    </section>
@endif
