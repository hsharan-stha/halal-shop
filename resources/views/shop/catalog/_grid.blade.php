@if ($products->isEmpty())
    <x-ui.empty-state icon="search" :title="__('shop.catalog.empty')" :description="__('shop.catalog.empty_hint')" />
@else
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        @foreach ($products as $product)
            <li><x-shop.product-card :product="$product" :wished="in_array($product->id, $wishlistIds, true)" /></li>
        @endforeach
    </ul>
    <div class="mt-6">{{ $products->links() }}</div>
@endif
