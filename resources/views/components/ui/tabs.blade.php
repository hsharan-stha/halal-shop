{{-- Keyboard-accessible tabs. $tabs: ['key' => 'Label']; panels use <div x-show="tab === 'key'"> --}}
@props(['tabs', 'active' => null])

<div x-data="{ tab: @js($active ?? array_key_first($tabs)) }" {{ $attributes }}>
    <div class="scrollbar-none -mx-4 mb-4 overflow-x-auto border-b border-line px-4 sm:mx-0 sm:px-0" role="tablist">
        <div class="flex min-w-max gap-1">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" id="tab-{{ $key }}" :aria-selected="(tab === @js($key)).toString()" :tabindex="tab === @js($key) ? 0 : -1"
                        @click="tab = @js($key)"
                        @keydown.arrow-right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                        @keydown.arrow-left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()"
                        class="min-h-11 border-b-2 px-3 text-sm font-medium whitespace-nowrap"
                        :class="tab === @js($key) ? 'border-primary text-primary' : 'border-transparent text-ink-muted hover:text-ink'">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>
    {{ $slot }}
</div>
