@props(['icon' => 'box', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-4 py-12 text-center']) }}>
    <div class="mb-4 grid size-14 place-items-center rounded-2xl bg-surface-muted text-ink-muted"><x-icon :name="$icon" class="size-7" /></div>
    <p class="text-base font-semibold">{{ $title }}</p>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-ink-muted">{{ $description }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
