@props(['product', 'wished' => false])

<article class="card relative flex h-full flex-col overflow-hidden">
    <a href="{{ route('products.show', $product->slug) }}" class="flex h-full flex-col">
        <div class="aspect-square bg-surface-muted">
            @if ($image = $product->primaryImage())
                <img src="{{ $image->url('small') }}" @if ($image->srcset()) srcset="{{ $image->srcset() }}" sizes="(min-width: 1024px) 25vw, 50vw" @endif alt="{{ $image->altText($product) }}" class="size-full object-cover" loading="lazy" width="400" height="400">
            @else
                <span class="grid size-full place-items-center text-ink-muted"><x-icon name="cube" class="size-10" /></span>
            @endif
        </div>
        <div class="flex flex-1 flex-col gap-1 p-3">
            @if ($product->shop)
                <p class="truncate text-xs text-primary">{{ $product->shop->name }}</p>
            @endif
            @if ($product->brand)
                <p class="truncate text-xs text-ink-muted">{{ $product->brand->localizedName() }}</p>
            @endif
            <h3 class="line-clamp-2 text-sm font-medium">{{ $product->localizedName() }}</h3>
            <div class="mt-auto flex flex-wrap items-center gap-1 pt-2">
                @if ($product->min_price !== null)
                    <x-price :amount="(int) $product->min_price" size="sm" />
                    @if ((int) $product->max_price > (int) $product->min_price)
                        <span class="text-xs text-ink-muted">{{ __('shop.product.price_from') }}</span>
                    @endif
                @endif
            </div>
            <div class="flex flex-wrap gap-1 pt-1">
                <x-ui.badge :color="$product->publicHalalStatus()->color()">{{ $product->publicHalalStatus()->label() }}</x-ui.badge>
                <x-ui.badge :color="$product->in_stock ? 'success' : 'neutral'">{{ $product->in_stock ? __('shop.catalog.in_stock') : __('shop.catalog.out_of_stock') }}</x-ui.badge>
            </div>
        </div>
    </a>
    @if (feature('wishlist_enabled'))
        <div class="absolute top-2 right-2">
            <x-shop.wishlist-button :product="$product" :wished="$wished" />
        </div>
    @endif
</article>
