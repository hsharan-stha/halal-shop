@props(['color' => 'neutral', 'icon' => null])

<span {{ $attributes->merge(['class' => 'badge badge-'.$color]) }}>
    @if ($icon)<x-icon :name="$icon" class="size-3.5" />@endif
    {{ $slot }}
</span>
