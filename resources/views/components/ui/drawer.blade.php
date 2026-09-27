{{-- Bottom sheet on mobile, side drawer from sm up. Open with: $dispatch('open-drawer', 'name') --}}
@props(['name', 'title' => null, 'side' => 'right'])

<div x-data="{ open: false }"
     x-on:open-drawer.window="if ($event.detail === @js($name)) open = true"
     x-on:close-drawer.window="if ($event.detail === @js($name)) open = false"
     x-on:keydown.escape.window="open = false"
     x-cloak x-show="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" @if ($title) aria-labelledby="drawer-{{ $name }}-title" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div x-show="open" x-trap.noscroll="open"
         x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full sm:translate-y-0 {{ $side === 'left' ? 'sm:-translate-x-full' : 'sm:translate-x-full' }}" x-transition:enter-end="translate-y-0 sm:translate-x-0"
         x-transition:leave="transition duration-150" x-transition:leave-start="translate-y-0 sm:translate-x-0" x-transition:leave-end="translate-y-full sm:translate-y-0 {{ $side === 'left' ? 'sm:-translate-x-full' : 'sm:translate-x-full' }}"
         @class([
             'safe-bottom absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col rounded-t-2xl bg-surface shadow-xl sm:inset-y-0 sm:max-h-none sm:w-96 sm:rounded-none',
             'sm:right-0 sm:left-auto' => $side === 'right',
             'sm:left-0 sm:right-auto' => $side === 'left',
         ])>
        <div class="flex items-center justify-between border-b border-line px-5 py-3">
            @if ($title)<h2 id="drawer-{{ $name }}-title" class="text-base font-semibold">{{ $title }}</h2>@endif
            <button type="button" class="btn btn-ghost btn-icon -mr-2 ml-auto" @click="open = false" aria-label="{{ __('shop.close') }}"><x-icon name="x" /></button>
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-4">{{ $slot }}</div>
        @isset($footer)
            <div class="border-t border-line px-5 py-3">{{ $footer }}</div>
        @endisset
    </div>
</div>
