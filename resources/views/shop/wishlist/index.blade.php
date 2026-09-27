<x-layouts.shop :title="__('shop.wishlist.title')" :description="__('shop.wishlist.empty_hint')" noindex>
    <div class="mx-auto max-w-3xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.wishlist.title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted" data-wishlist-page-count>{{ trans_choice('shop.wishlist.count', $products->count(), ['count' => $products->count()]) }}</p>

        @if ($products->isEmpty())
            <x-ui.empty-state class="mt-8" icon="heart" :title="__('shop.wishlist.empty')" :description="__('shop.wishlist.empty_hint')">
                <x-ui.button :href="route('shop.index')">{{ __('shop.home.shop_now') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="mt-6 space-y-3" data-wishlist-list>
                @foreach ($products as $product)
                    @php($choices = $product->variants->filter(fn ($variant) => ($variant->inventoryItem?->sellableQuantity() ?? 0) >= max(1, (int) $product->min_order_quantity)))
                    <li class="card p-4" data-wishlist-item>
                        <div class="flex items-start gap-3">
                            <a href="{{ route('products.show', $product->slug) }}" class="size-20 shrink-0 overflow-hidden rounded-xl bg-surface-muted sm:size-24">
                                @if ($image = $product->primaryImage())
                                    <img src="{{ $image->url('small') }}" alt="{{ $image->altText($product) }}" class="size-full object-cover" width="96" height="96">
                                @else
                                    <span class="grid size-full place-items-center text-ink-muted"><x-icon name="cube" class="size-8" /></span>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                @if ($product->brand)
                                    <p class="truncate text-xs text-ink-muted">{{ $product->brand->localizedName() }}</p>
                                @endif
                                <a href="{{ route('products.show', $product->slug) }}" class="font-medium hover:text-primary">{{ $product->localizedName() }}</a>
                                @if ($product->min_price !== null)
                                    <p class="mt-1 text-sm tabular-nums">
                                        <x-price :amount="(int) $product->min_price" size="sm" />
                                        @if ((int) $product->max_price > (int) $product->min_price)
                                            <span class="text-ink-muted">{{ __('shop.product.price_from') }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <x-shop.wishlist-button :product="$product" wished />
                        </div>

                        @if ($choices->isEmpty())
                            <p class="mt-3 text-sm text-danger">{{ __('shop.cart.unavailable') }}</p>
                        @else
                            <form method="POST" action="{{ route('wishlist.cart', $product->slug) }}" class="mt-4 flex flex-col gap-3">
                                @csrf
                                @if ($choices->count() > 1)
                                    <div>
                                        <label for="move-{{ $product->id }}" class="form-label">{{ __('shop.product.variants') }}</label>
                                        <select id="move-{{ $product->id }}" name="variant_id" class="form-control">
                                            @foreach ($choices as $variant)
                                                <option value="{{ $variant->id }}">{{ $variant->translate('name') ?: $variant->sku }} · {{ money($variant->price) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <input type="hidden" name="variant_id" value="{{ $choices->first()->id }}">
                                @endif
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="w-24">
                                        <label for="qty-{{ $product->id }}" class="form-label">{{ __('shop.cart.quantity') }}</label>
                                        <input id="qty-{{ $product->id }}" type="number" name="quantity" value="{{ max(1, (int) $product->min_order_quantity) }}" min="1" max="999" inputmode="numeric" class="form-control w-24">
                                    </div>
                                    <x-ui.button icon="cart">{{ __('shop.cart.add') }}</x-ui.button>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.shop>
