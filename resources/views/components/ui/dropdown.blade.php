@props(['align' => 'right', 'width' => 'w-48', 'label' => null])

<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <div @click="open = !open">{{ $trigger }}</div>
    <div x-cloak x-show="open" x-transition.opacity @click="open = false"
         class="absolute z-40 mt-2 {{ $width }} {{ $align === 'left' ? 'left-0' : 'right-0' }} overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-lg" role="menu" @if ($label) aria-label="{{ $label }}" @endif>
        {{ $slot }}
    </div>
</div>
