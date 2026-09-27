@props(['title' => null])

@php($sections = \App\Support\Navigation::admin(auth()->user()))

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="($title ? $title.' · ' : '').__('admin.title')" :noindex="true" />
</head>
<body class="min-h-dvh" x-data="{ drawer: false, collapsed: $persist(false).as('admin_sidebar_collapsed') }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">{{ __('shop.skip_to_content') }}</a>

    {{-- Mobile / tablet drawer --}}
    <div x-cloak x-show="drawer" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('admin.nav.menu') }}" @keydown.escape.window="drawer = false">
        <div x-show="drawer" x-transition.opacity class="absolute inset-0 bg-black/50" @click="drawer = false"></div>
        <aside x-show="drawer" x-trap.noscroll="drawer" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               class="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-surface shadow-xl">
            <div class="flex h-14 items-center justify-between border-b border-line px-4">
                <span class="truncate font-bold">{{ $branding->name() }}</span>
                <button type="button" class="btn btn-ghost btn-icon -mr-2" @click="drawer = false" aria-label="{{ __('shop.close') }}"><x-icon name="x" /></button>
            </div>
            <x-admin.nav :sections="$sections" />
        </aside>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden flex-col border-r border-line bg-surface transition-[width] lg:flex" :class="collapsed ? 'w-18' : 'w-64'">
        <div class="flex h-16 items-center gap-2 border-b border-line px-4">
            <a href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-2">
                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-sm font-bold text-on-primary">{{ mb_substr($branding->shortName(), 0, 1) }}</span>
                <span class="truncate font-bold" x-show="!collapsed">{{ $branding->name() }}</span>
            </a>
        </div>
        <x-admin.nav :sections="$sections" collapsible />
    </aside>

    <div class="transition-[padding]" :class="collapsed ? 'lg:pl-18' : 'lg:pl-64'">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-line bg-surface/95 px-3 backdrop-blur sm:h-16 sm:px-6">
            <button type="button" class="btn btn-ghost btn-icon lg:hidden" @click="drawer = true" aria-label="{{ __('admin.nav.menu') }}"><x-icon name="menu" /></button>
            <button type="button" class="btn btn-ghost btn-icon hidden lg:inline-flex" @click="collapsed = !collapsed" :aria-expanded="(!collapsed).toString()" aria-label="{{ __('admin.nav.toggle_sidebar') }}"><x-icon name="menu" /></button>
            <p class="truncate text-sm font-semibold sm:text-base">{{ $title }}</p>

            <div class="ml-auto flex items-center gap-1">
                <a href="{{ route('home') }}" class="btn btn-ghost btn-icon" target="_blank" rel="noopener" aria-label="{{ __('admin.view_store') }}"><x-icon name="store" /></a>
                @if ($branding->darkModeEnabled())
                    <button type="button" class="btn btn-ghost btn-icon" x-data @click="window.setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark')" aria-label="{{ __('shop.theme.toggle') }}">
                        <x-icon name="moon" class="dark:hidden" /><x-icon name="sun" class="hidden dark:block" />
                    </button>
                @endif
                <x-locale-switcher />
                <x-ui.dropdown :label="__('admin.account_menu')">
                    <x-slot:trigger>
                        <button type="button" class="btn btn-ghost gap-2 px-2" aria-haspopup="true">
                            <span class="grid size-8 place-items-center rounded-full bg-primary-soft text-xs font-bold text-primary">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                            <span class="hidden max-w-32 truncate text-sm md:inline">{{ auth()->user()->name }}</span>
                        </button>
                    </x-slot:trigger>
                    <p class="truncate px-4 py-2 text-xs text-ink-muted">{{ auth()->user()->email }}</p>
                    <a href="{{ route('account.security') }}" class="block px-4 py-2.5 text-sm hover:bg-surface-muted" role="menuitem">{{ __('shop.account.nav.security') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-danger hover:bg-surface-muted" role="menuitem"><x-icon name="logout" class="size-4" />{{ __('shop.auth.logout') }}</button>
                    </form>
                </x-ui.dropdown>
            </div>
        </header>

        <x-flash />

        <main id="main" class="mx-auto max-w-[100rem] px-3 py-5 sm:px-6 sm:py-6">
            {{ $slot }}
        </main>
    </div>

    <x-toasts />
    @livewireScripts
    {{ $scripts ?? '' }}
</body>
</html>
