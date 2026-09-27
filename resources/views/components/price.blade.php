{{-- Price display. Amounts are integer yen. --}}
@props(['amount', 'compare' => null, 'size' => 'base', 'taxNote' => false])

@php
    $sizes = ['sm' => 'text-sm', 'base' => 'text-base', 'lg' => 'text-xl', 'xl' => 'text-2xl sm:text-3xl'];
    $onSale = $compare !== null && $compare > $amount;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-baseline gap-x-2']) }}>
    <span @class(['font-bold tabular-nums', $sizes[$size] ?? 'text-base', 'text-danger' => $onSale])>{{ money($amount) }}</span>
    @if ($onSale)
        <span class="text-xs text-ink-muted line-through tabular-nums"><span class="sr-only">{{ __('shop.product.regular_price') }}</span>{{ money($compare) }}</span>
        <span class="badge badge-danger">-{{ (int) floor((($compare - $amount) / $compare) * 100) }}%</span>
    @endif
    @if ($taxNote)
        <span class="text-xs text-ink-muted">{{ settings('tax.prices_include_tax') ? __('shop.tax.included') : __('shop.tax.excluded') }}</span>
    @endif
</span>
