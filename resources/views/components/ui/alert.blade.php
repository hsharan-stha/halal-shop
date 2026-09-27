@props(['type' => 'info', 'dismissible' => false, 'title' => null])

@php
    $styles = [
        'success' => ['bg-success-soft text-success border-success/30', 'check-circle'],
        'danger' => ['bg-danger-soft text-danger border-danger/30', 'alert'],
        'warning' => ['bg-warning-soft text-warning border-warning/30', 'alert'],
        'info' => ['bg-info-soft text-info border-info/30', 'info'],
    ];
    [$classes, $icon] = $styles[$type] ?? $styles['info'];
@endphp

<div x-data="{ open: true }" x-show="open" {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {$classes}"]) }} role="{{ in_array($type, ['danger', 'warning']) ? 'alert' : 'status' }}">
    <x-icon :name="$icon" class="mt-0.5 size-5" />
    <div class="min-w-0 flex-1 text-ink">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" class="-m-1 rounded p-1 text-ink-muted hover:text-ink" @click="open = false" aria-label="{{ __('shop.close') }}"><x-icon name="x" class="size-4" /></button>
    @endif
</div>
