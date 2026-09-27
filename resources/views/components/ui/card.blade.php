@props(['title' => null, 'description' => null, 'padding' => 'p-4 sm:p-6'])

<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-6">
            <div class="min-w-0">
                @if ($title)<h2 class="text-base font-semibold">{{ $title }}</h2>@endif
                @if ($description)<p class="text-sm text-ink-muted">{{ $description }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="{{ $padding }}">{{ $slot }}</div>
</section>
