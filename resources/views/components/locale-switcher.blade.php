@php($locales = app(\App\Services\LocaleService::class)->enabled())

@if (count($locales) > 1)
    <div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="btn btn-ghost btn-icon gap-1 px-2" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" aria-label="{{ __('shop.nav.language') }}">
            <x-icon name="globe" />
            <span class="text-xs font-semibold uppercase">{{ app()->getLocale() }}</span>
        </button>
        <div x-cloak x-show="open" x-transition.opacity class="absolute right-0 z-40 mt-2 w-40 overflow-hidden rounded-xl border border-line bg-surface shadow-lg" role="menu">
            @foreach ($locales as $code => $locale)
                <form method="POST" action="{{ route('locale.switch', $code) }}">
                    @csrf
                    <button type="submit" role="menuitem" @class(['flex w-full items-center justify-between px-4 py-3 text-left text-sm hover:bg-surface-muted', 'font-semibold text-primary' => app()->getLocale() === $code]) lang="{{ $code }}">
                        {{ $locale['native'] }}
                        @if (app()->getLocale() === $code)<x-icon name="check" class="size-4" />@endif
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif
