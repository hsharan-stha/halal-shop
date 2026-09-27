@props(['title' => null, 'description' => null, 'noindex' => false, 'canonical' => null, 'ogImage' => null, 'ogType' => 'website', 'hideBottomNav' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="$title" :description="$description" :noindex="$noindex" :canonical="$canonical" :og-image="$ogImage" :og-type="$ogType" />
    {{ $head ?? '' }}
</head>
<body class="min-h-dvh">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">{{ __('shop.skip_to_content') }}</a>

    @php($bottomNav = \App\Support\Navigation::bottom())

    <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/85">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 sm:h-16 lg:px-6">
            <a href="{{ route('home') }}" class="flex min-w-0 shrink-0 items-center gap-2" aria-label="{{ $branding->name() }}">
                @if ($logo = $branding->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $branding->name() }}" class="h-8 w-auto max-w-36 object-contain sm:h-9">
                @else
                    <span class="grid size-9 place-items-center rounded-xl bg-primary text-sm font-bold text-on-primary">{{ mb_substr($branding->shortName(), 0, 1) }}</span>
                    <span class="truncate text-base font-bold sm:text-lg">{{ $branding->name() }}</span>
                @endif
            </a>

            @if (Route::has('search'))
                <form action="{{ route('search') }}" method="GET" role="search" class="ml-4 hidden flex-1 md:block">
                    <label for="header-search" class="sr-only">{{ __('shop.search.placeholder') }}</label>
                    <div class="relative max-w-xl">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-ink-muted" />
                        <input id="header-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('shop.search.placeholder') }}" class="form-control rounded-full pl-10" autocomplete="off" enterkeyhint="search">
                    </div>
                </form>
            @else
                <div class="flex-1"></div>
            @endif

            <nav class="ml-auto flex items-center gap-1" aria-label="{{ __('shop.nav.utility') }}">
                <x-locale-switcher class="hidden sm:block" />

                @if (Route::has('search'))
                    <a href="{{ route('search') }}" class="btn btn-ghost btn-icon md:hidden" aria-label="{{ __('shop.nav.search') }}"><x-icon name="search" /></a>
                @endif

                @if (Route::has('account.wishlist') && feature('wishlist_enabled'))
                    @php($wishlistCount = count(app(\App\Services\Catalog\WishlistService::class)->ids()))
                    <a href="{{ route('account.wishlist') }}" @class(['btn btn-ghost btn-icon relative', 'text-primary' => request()->routeIs('account.wishlist')]) aria-label="{{ $wishlistCount > 0 ? trans_choice('shop.wishlist.count', $wishlistCount, ['count' => $wishlistCount]) : __('shop.nav.wishlist') }}" @if (request()->routeIs('account.wishlist')) aria-current="page" @endif>
                        <x-icon name="heart" :solid="$wishlistCount > 0" @class(['text-danger' => $wishlistCount > 0]) />
                        @if ($wishlistCount > 0)
                            <span data-wishlist-count="{{ $wishlistCount }}" class="absolute top-1 right-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] leading-none font-bold text-on-primary">{{ $wishlistCount > 99 ? '99+' : $wishlistCount }}</span>
                        @endif
                    </a>
                @endif

                @auth
                    <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard') }}" class="btn btn-ghost btn-icon hidden lg:inline-flex" aria-label="{{ __('shop.nav.account') }}"><x-icon name="user" /></a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-ghost hidden text-sm lg:inline-flex">{{ __('shop.auth.login') }}</a>
                @endauth

                @if (Route::has('cart.index'))
                    <a href="{{ route('cart.index') }}" class="btn btn-ghost btn-icon relative" aria-label="{{ __('shop.nav.cart') }}">
                        <x-icon name="cart" />
                        <livewire:cart-count />
                    </a>
                @endif
            </nav>
        </div>

        {{ $subheader ?? '' }}
    </header>

    <x-flash />

    <main id="main" @class(['pb-24 lg:pb-0' => ! $hideBottomNav && $bottomNav !== []])>
        {{ $slot }}
    </main>

    <x-shop.footer />

    @if (! $hideBottomNav && $bottomNav !== [])
        <nav class="safe-bottom fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 backdrop-blur lg:hidden" aria-label="{{ __('shop.nav.primary') }}">
            <ul class="mx-auto grid max-w-lg" style="grid-template-columns: repeat({{ count($bottomNav) }}, minmax(0, 1fr))">
                @foreach ($bottomNav as $item)
                    @php($active = request()->routeIs($item['active']))
                    <li>
                        <a href="{{ route($item['route']) }}" @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium', 'text-primary' => $active, 'text-ink-muted' => ! $active]) @if ($active) aria-current="page" @endif>
                            <span class="relative">
                                <x-icon :name="$item['icon']" class="size-6" />
                                @if ($item['route'] === 'cart.index')
                                    <livewire:cart-count :compact="true" />
                                @endif
                            </span>
                            {{ __($item['label']) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    <x-toasts />
    <x-cookie-notice />

    @livewireScripts
    {{ $scripts ?? '' }}
</body>
</html>
