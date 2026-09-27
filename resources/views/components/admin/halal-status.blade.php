{{-- Admin view of a product's halal status, flagging "certified" without valid certificate data. --}}
@props(['product'])

<span class="inline-flex flex-wrap items-center gap-1">
    <x-ui.badge :color="$product->halal_status->color()" :icon="$product->halal_status->requiresCertificate() ? 'shield-check' : null">{{ $product->halal_status->label() }}</x-ui.badge>
    @if ($product->halal_status->requiresCertificate() && ! $product->isCertifiedHalal())
        <x-ui.badge color="warning" icon="alert">{{ __('admin.halal.not_publicly_certified') }}</x-ui.badge>
    @endif
</span>
