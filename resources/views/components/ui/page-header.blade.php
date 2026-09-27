@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-2 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink"><x-icon name="chevron-left" class="size-4" />{{ __('shop.back') }}</a>
        @endif
        <h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
        @if ($description)<p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
