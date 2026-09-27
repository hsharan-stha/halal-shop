@props(['row', 'platform' => true])

@if ($row->isPlatformOwned())
    @if ($platform)
        <x-ui.badge color="neutral">{{ __('admin.owner_platform') }}</x-ui.badge>
    @endif
@elseif ($name = $row->ownerShopName())
    <x-ui.badge color="info">{{ $name }}</x-ui.badge>
@endif
