@props(['lines' => 3])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-2']) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="h-4 rounded bg-surface-muted" style="width: {{ 100 - ($i * 17 % 40) }}%"></div>
    @endfor
</div>
