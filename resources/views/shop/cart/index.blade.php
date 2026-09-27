<x-layouts.shop :title="__('shop.cart.title')" noindex>
    <div class="mx-auto max-w-3xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.cart.title') }}</h1>

        @if ($quote['lines'] === [] && $unavailable->isEmpty())
            <x-ui.empty-state class="mt-8" icon="cart" :title="__('shop.cart.empty')" :description="__('shop.cart.empty_hint')">
                <x-ui.button :href="route('shop.index')">{{ __('shop.home.shop_now') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="mt-6 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-surface">
                @foreach ($quote['lines'] as $line)
                    @php($variant = $line['variant'])
                    <li class="space-y-3 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('products.show', ['product' => $variant->product->slug, 'variant' => $variant->id]) }}" class="font-medium hover:text-primary">{{ $variant->product->localizedName() }}</a>
                                @if ($variant->translate('name'))
                                    <p class="text-sm text-ink-muted">{{ $variant->translate('name') }}</p>
                                @endif
                                <p class="text-sm tabular-nums text-ink-muted">{{ money($line['unit_price']) }}</p>
                            </div>
                            <p class="text-sm font-semibold tabular-nums">{{ money($line['line_total']) }}</p>
                        </div>
                        <div class="flex flex-wrap items-end gap-2">
                            <form method="POST" action="{{ route('cart.update', $variant) }}" class="flex items-end gap-2">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="qty-{{ $variant->id }}" class="form-label">{{ __('shop.cart.quantity') }}</label>
                                    <input id="qty-{{ $variant->id }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="999" inputmode="numeric" class="form-control w-24">
                                </div>
                                <x-ui.button variant="secondary" size="sm">{{ __('shop.cart.update') }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('cart.destroy', $variant) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost" size="sm" type="submit">{{ __('shop.cart.remove') }}</x-ui.button>
                            </form>
                        </div>
                    </li>
                @endforeach

                @foreach ($unavailable as $variant)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $variant->product?->localizedName() ?? $variant->sku }}</p>
                            <p class="text-sm text-danger">{{ __('shop.cart.unavailable') }}</p>
                        </div>
                        <form method="POST" action="{{ route('cart.destroy', $variant) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="ghost" size="sm">{{ __('shop.cart.remove') }}</x-ui.button>
                        </form>
                    </li>
                @endforeach
            </ul>

            @if ($quote['lines'] !== [])
                <dl class="mt-6 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>{{ __('shop.cart.subtotal') }}</dt><dd class="tabular-nums">{{ money($quote['items_total']) }}</dd></div>
                    @if (settings('tax.show_tax_breakdown'))
                        <div class="flex justify-between gap-4 text-ink-muted"><dt>{{ __('shop.cart.tax') }}</dt><dd class="tabular-nums">{{ money($quote['tax_total']) }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4"><dt>{{ __('shop.cart.shipping') }}</dt><dd class="tabular-nums">{{ money($quote['shipping_total']) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-line pt-2 text-base font-semibold"><dt>{{ __('shop.cart.total') }}</dt><dd class="tabular-nums">{{ money($quote['total']) }}</dd></div>
                </dl>
                <div class="mt-6">
                    <x-ui.button :href="route('checkout.create')">{{ __('shop.cart.checkout') }}</x-ui.button>
                </div>
            @endif
        @endif
    </div>
</x-layouts.shop>
