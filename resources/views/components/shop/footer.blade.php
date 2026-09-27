@php
    $pageLinks = Route::has('pages.show') ? [
        'about' => 'shop.footer.about',
        'halal-information' => 'shop.footer.halal',
        'faq' => 'shop.footer.faq',
        'shipping-policy' => 'shop.footer.shipping',
        'return-policy' => 'shop.footer.returns',
        'terms' => 'shop.footer.terms',
        'privacy' => 'shop.footer.privacy',
        'commercial-transactions' => 'shop.footer.commercial',
    ] : [];
    $social = $branding->socialLinks();
@endphp

<footer class="mt-12 border-t border-line bg-surface">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4 lg:px-6">
        <div class="space-y-3">
            <p class="text-lg font-bold">{{ $branding->name() }}</p>
            @if ($hours = settings()->translated('general.business_hours'))
                <p class="text-sm text-ink-muted">{{ $hours }}</p>
            @endif
            @if ($email = settings('branding.support_email'))
                <p class="flex items-center gap-2 text-sm"><x-icon name="mail" class="size-4 text-ink-muted" /><a href="mailto:{{ $email }}" class="hover:text-primary">{{ $email }}</a></p>
            @endif
            @if ($phone = settings('branding.support_phone'))
                <p class="flex items-center gap-2 text-sm"><x-icon name="phone" class="size-4 text-ink-muted" /><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-primary">{{ $phone }}</a></p>
            @endif
        </div>

        @if ($pageLinks)
            <nav class="sm:col-span-1 lg:col-span-2" aria-label="{{ __('shop.footer.information') }}">
                <p class="mb-3 text-sm font-semibold">{{ __('shop.footer.information') }}</p>
                <ul class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-ink-muted">
                    @foreach ($pageLinks as $slug => $label)
                        <li><a href="{{ route('pages.show', $slug) }}" class="inline-block py-1 hover:text-primary">{{ __($label) }}</a></li>
                    @endforeach
                    @if (Route::has('contact'))
                        <li><a href="{{ route('contact') }}" class="inline-block py-1 hover:text-primary">{{ __('shop.footer.contact') }}</a></li>
                    @endif
                </ul>
            </nav>
        @endif

        <nav aria-label="{{ __('shop.footer.shop') }}">
            <p class="mb-3 text-sm font-semibold">{{ __('shop.footer.shop') }}</p>
            <ul class="space-y-2 text-sm text-ink-muted">
                <li><a href="{{ route('shop.index') }}" class="inline-block py-1 hover:text-primary">{{ __('shop.catalog.title') }}</a></li>
                <li><a href="{{ route('categories.index') }}" class="inline-block py-1 hover:text-primary">{{ __('shop.categories.title') }}</a></li>
                <li><a href="{{ route('search') }}" class="inline-block py-1 hover:text-primary">{{ __('shop.search.title') }}</a></li>
                @if (Route::has('account.wishlist') && feature('wishlist_enabled'))
                    <li><a href="{{ route('account.wishlist') }}" class="inline-block py-1 hover:text-primary">{{ __('shop.nav.wishlist') }}</a></li>
                @endif
            </ul>
        </nav>

        <div class="space-y-3">
            @if ($social)
                <p class="text-sm font-semibold">{{ __('shop.footer.follow') }}</p>
                <ul class="flex flex-wrap gap-2 text-sm">
                    @foreach ($social as $network => $url)
                        <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">{{ __('shop.social.'.$network) }}</a></li>
                    @endforeach
                </ul>
            @endif
            <x-locale-switcher class="sm:hidden" />
        </div>
    </div>
    <div class="border-t border-line">
        <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-ink-muted lg:px-6">&copy; {{ now(config('app.display_timezone'))->year }} {{ settings('general.company_name') ?: $branding->name() }}</p>
    </div>
</footer>
