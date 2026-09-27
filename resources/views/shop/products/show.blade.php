@php
    $halal = $product->publicHalalStatus();
    $images = $product->images->map(fn ($image) => [
        'url' => $image->url('large'),
        'alt' => $image->altText($product),
    ])->values();
    $variants = $product->variants->map(function ($variant) {
        $sellable = $variant->inventoryItem?->sellableQuantity() ?? 0;
        $low = $variant->inventoryItem?->lowStockThreshold() ?? (int) settings('inventory.low_stock_threshold', 5);

        return [
            'id' => $variant->id,
            'label' => $variant->translate('name') ?: $variant->sku,
            'price' => money($variant->price),
            'compare' => $variant->isOnSale() ? money($variant->compare_at_price) : null,
            'percent' => $variant->discountPercent(),
            'sku' => $variant->sku,
            'available' => $sellable > 0,
            'stock' => $sellable < 1
                ? __('shop.product.out_of_stock')
                : ($sellable <= $low
                    ? trans_choice('shop.product.low_stock', $sellable, ['count' => $sellable])
                    : __('shop.product.in_stock')),
        ];
    })->values();
    $current = $variants->firstWhere('id', $selected?->id) ?? $variants->first();
@endphp

<x-layouts.shop
    :title="$product->translate('meta_title') ?: $product->localizedName()"
    :description="$product->translate('meta_description') ?: $product->translate('short_description')"
    :canonical="route('products.show', $product->slug)"
    :og-image="$product->primaryImage()?->url('medium')"
    og-type="product"
>
    <div class="mx-auto max-w-7xl px-4 py-6 lg:px-6" x-data="{
        selected: {{ (int) ($current['id'] ?? 0) }},
        image: 0,
        variants: @js($variants),
        images: @js($images),
        current() { return this.variants.find((variant) => variant.id === this.selected) ?? this.variants[0] ?? null; },
        photo() { return this.images[this.image] ?? null; },
    }">
        <nav class="mb-4 text-sm text-ink-muted" aria-label="{{ __('shop.breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('shop.nav.home') }}</a>
            @if ($product->category?->parent?->is_active)
                <span aria-hidden="true"> / </span>
                <a href="{{ route('categories.show', $product->category->parent) }}" class="hover:text-ink">{{ $product->category->parent->localizedName() }}</a>
            @endif
            @if ($product->category)
                <span aria-hidden="true"> / </span>
                <a href="{{ route('categories.show', $product->category) }}" class="hover:text-ink">{{ $product->category->localizedName() }}</a>
            @endif
        </nav>

        <div class="grid gap-8 lg:grid-cols-2">
            <div class="min-w-0">
                <div class="card overflow-hidden">
                    <div class="aspect-square bg-surface-muted">
                        @if ($first = $images->first())
                            <img :src="photo()?.url" :alt="photo()?.alt" src="{{ $first['url'] }}" alt="{{ $first['alt'] }}" class="size-full object-contain" width="800" height="800">
                        @else
                            <span class="grid size-full place-items-center text-ink-muted"><x-icon name="cube" class="size-16" /></span>
                        @endif
                    </div>
                </div>
                @if ($images->count() > 1)
                    <ul class="mt-3 flex gap-2 overflow-x-auto">
                        @foreach ($images as $index => $image)
                            <li>
                                <button type="button" class="size-16 overflow-hidden rounded-lg border border-line" x-on:click="image = {{ $index }}" :aria-current="image === {{ $index }} ? 'true' : null">
                                    <img src="{{ $image['url'] }}" alt="" class="size-full object-cover">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="min-w-0">
                @if ($product->brand && ! $product->brand->trashed() && $product->brand->is_active)
                    <a href="{{ route('brands.show', $product->brand) }}" class="text-sm font-medium text-primary hover:underline">{{ $product->brand->localizedName() }}</a>
                @elseif ($product->brand)
                    <p class="text-sm text-ink-muted">{{ $product->brand->localizedName() }}</p>
                @endif
                <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ $product->localizedName() }}</h1>

                <div class="mt-3 flex flex-wrap gap-2">
                    <x-ui.badge :color="$halal->color()">{{ $halal->label() }}</x-ui.badge>
                    <x-ui.badge :color="$product->storage_type->color()">{{ $product->storage_type->label() }}</x-ui.badge>
                </div>

                @if ($current)
                    <div class="mt-4">
                        <p class="text-2xl font-bold tabular-nums">
                            <span x-text="current().price">{{ $current['price'] }}</span>
                            <span class="ml-2 text-sm font-normal text-ink-muted line-through" x-show="current().compare" x-text="current().compare">{{ $current['compare'] }}</span>
                        </p>
                        <p class="text-xs text-ink-muted">{{ settings('tax.prices_include_tax') ? __('shop.tax.included') : __('shop.tax.excluded') }}</p>
                        <p class="mt-2 text-sm font-medium" :class="current().available ? 'text-success' : 'text-danger'" x-text="current().stock">{{ $current['stock'] }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('shop.product.sku') }} <span class="font-mono" x-text="current().sku">{{ $current['sku'] }}</span></p>
                    </div>
                @endif

                @if ($variants->count() > 1)
                    <div class="mt-4">
                        <p class="mb-2 text-sm font-semibold">{{ __('shop.product.variants') }}</p>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($variants as $variant)
                                <li>
                                    <a href="{{ route('products.show', ['product' => $product->slug, 'variant' => $variant['id']]) }}"
                                       class="btn btn-secondary btn-sm"
                                       :class="selected === {{ $variant['id'] }} ? 'border-primary text-primary' : ''"
                                       :aria-current="selected === {{ $variant['id'] }} ? 'true' : null"
                                       x-on:click.prevent="selected = {{ $variant['id'] }}">{{ $variant['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($current)
                    <form method="POST" action="{{ route('cart.store') }}" class="mt-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $current['id'] }}" :value="selected">
                        <div>
                            <label for="cart-quantity" class="form-label">{{ __('shop.cart.quantity') }}</label>
                            <input id="cart-quantity" type="number" name="quantity" value="{{ max(1, (int) $product->min_order_quantity) }}" min="1" max="999" inputmode="numeric" class="form-control w-24">
                        </div>
                        <x-ui.button icon="cart" :disabled="! $current['available']" x-bind:disabled="!current()?.available">{{ __('shop.cart.add') }}</x-ui.button>
                    </form>
                @endif

                @if ($product->translate('short_description'))
                    <p class="mt-4 text-sm">{{ $product->translate('short_description') }}</p>
                @endif

                @if (feature('wishlist_enabled'))
                    <div class="mt-4">
                        <x-shop.wishlist-button :product="$product" :wished="in_array($product->id, $wishlistIds, true)" />
                    </div>
                @endif

                @if ($product->min_order_quantity > 1 || $product->max_order_quantity)
                    <p class="mt-3 text-xs text-ink-muted">{{ __('shop.product.order_quantity', ['min' => $product->min_order_quantity, 'max' => $product->max_order_quantity ?: '—']) }}</p>
                @endif
            </div>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            @if ($product->translate('description'))
                <section class="card p-4 sm:p-6 lg:col-span-2">
                    <h2 class="text-lg font-semibold">{{ __('shop.product.description') }}</h2>
                    <div class="mt-3 text-sm whitespace-pre-line">{{ $product->translate('description') }}</div>
                </section>
            @endif

            <section class="card p-4 sm:p-6">
                <h2 class="text-lg font-semibold">{{ __('shop.product.halal') }}</h2>
                <p class="mt-2"><x-ui.badge :color="$halal->color()">{{ $halal->label() }}</x-ui.badge></p>
                @if ($product->translate('halal_notes'))
                    <p class="mt-3 text-sm whitespace-pre-line">{{ $product->translate('halal_notes') }}</p>
                @endif
                @if ($halal === \App\Enums\HalalStatus::Certified && $product->halalCertifications->isNotEmpty())
                    <ul class="mt-4 space-y-3">
                        @foreach ($product->halalCertifications as $certificate)
                            <li class="rounded-xl border border-line p-3 text-sm">
                                <p class="font-medium">{{ $certificate->certifying_body }}</p>
                                <p class="text-ink-muted">{{ __('shop.product.certificate_number') }} <span class="font-mono">{{ $certificate->certificate_number }}</span></p>
                                <p class="text-ink-muted">{{ __('shop.product.certificate_expires', ['date' => local_date($certificate->expires_at)]) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($product->hasReviewedFoodLabel())
                <section class="card p-4 sm:p-6">
                    <h2 class="text-lg font-semibold">{{ __('shop.product.label') }}</h2>
                    <dl class="mt-3 space-y-3 text-sm">
                        @if ($product->translate('ingredients'))
                            <div>
                                <dt class="font-medium">{{ __('shop.product.ingredients') }}</dt>
                                <dd class="whitespace-pre-line">{{ $product->translate('ingredients') }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="font-medium">{{ __('shop.product.allergens') }}</dt>
                            <dd>
                                @if ($product->allergens?->isNotEmpty())
                                    {{ $product->allergens->map(fn ($allergen) => $allergen->label())->join(app()->getLocale() === 'ja' ? '、' : ', ') }}
                                @else
                                    {{ __('shop.product.no_allergens_listed') }}
                                @endif
                            </dd>
                        </div>
                        @if (is_array($product->nutrition) && $product->nutrition !== [])
                            <div>
                                <dt class="font-medium">{{ __('shop.product.nutrition') }}@if (filled($product->nutrition['basis'] ?? null))（{{ $product->nutrition['basis'] }}）@endif</dt>
                                <dd>
                                    <ul class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                                        @foreach (['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'salt_g'] as $field)
                                            @if (filled($product->nutrition[$field] ?? null))
                                                <li class="flex justify-between gap-2"><span class="text-ink-muted">{{ __('shop.product.nutrition_fields.'.$field) }}</span><span class="tabular-nums">{{ $product->nutrition[$field] }}</span></li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </dd>
                            </div>
                        @endif
                        @foreach (['net_content' => 'net_content', 'country_of_origin' => 'origin', 'manufacturer' => 'manufacturer', 'importer' => 'importer'] as $field => $label)
                            @if (filled($product->{$field}))
                                <div>
                                    <dt class="font-medium">{{ __('shop.product.'.$label) }}</dt>
                                    <dd>{{ $field === 'country_of_origin' ? country_name($product->country_of_origin) : $product->{$field} }}</dd>
                                </div>
                            @endif
                        @endforeach
                        @if ($product->translate('storage_instructions'))
                            <div>
                                <dt class="font-medium">{{ __('shop.product.storage') }}</dt>
                                <dd class="whitespace-pre-line">{{ $product->translate('storage_instructions') }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif
        </div>

        <x-shop.product-section :title="__('shop.product.related')" :products="$related" :wishlist-ids="$wishlistIds" />
        <x-shop.product-section :title="__('shop.home.recent')" :products="$recent" :wishlist-ids="$wishlistIds" />
    </div>
</x-layouts.shop>
