@php($range = $product->priceRange())
@if ($range === null)
    <span class="text-ink-muted">—</span>
@elseif ($range['min'] === $range['max'])
    {{ money($range['min']) }}
@else
    {{ money($range['min']) }} – {{ money($range['max']) }}
@endif
