{{-- Accessible modal. Open with: $dispatch('open-modal', 'name') --}}
@props(['name', 'title' => null, 'maxWidth' => 'max-w-lg'])

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === @js($name)) { open = true; $nextTick(() => $refs.panel.focus()) }"
     x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
     x-on:keydown.escape.window="open = false"
     x-cloak x-show="open"
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
     role="dialog" aria-modal="true" @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="open = false"></div>
    <div x-show="open" x-trap.noscroll="open" x-ref="panel" tabindex="-1" x-transition
         class="relative max-h-[90dvh] w-full {{ $maxWidth }} overflow-y-auto rounded-t-2xl bg-surface p-5 shadow-xl outline-none sm:rounded-2xl">
        <div class="mb-4 flex items-start justify-between gap-4">
            @if ($title)<h2 id="modal-{{ $name }}-title" class="text-lg font-semibold">{{ $title }}</h2>@endif
            <button type="button" class="btn btn-ghost btn-icon -m-2 ml-auto" @click="open = false" aria-label="{{ __('shop.close') }}"><x-icon name="x" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
