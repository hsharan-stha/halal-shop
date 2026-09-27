{{-- Only strictly necessary cookies (session, CSRF, locale) are used; this notice informs, it does not track. --}}
@if (settings('privacy.cookie_notice_enabled'))
    <div x-data="{ show: ! localStorage.getItem('cookie_notice_ack') }" x-cloak x-show="show"
         class="fixed inset-x-3 bottom-20 z-40 mx-auto max-w-xl rounded-2xl border border-line bg-surface p-4 text-sm shadow-xl lg:bottom-4" role="region" aria-label="{{ __('shop.cookies.title') }}">
        <p class="text-ink-muted">{{ settings()->translated('privacy.cookie_notice_text') ?: __('shop.cookies.default_text') }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-end gap-2">
            @if (Route::has('pages.show'))
                <a href="{{ route('pages.show', 'privacy') }}" class="btn btn-ghost btn-sm">{{ __('shop.cookies.learn_more') }}</a>
            @endif
            <button type="button" class="btn btn-primary btn-sm" @click="localStorage.setItem('cookie_notice_ack', '1'); show = false">{{ __('shop.cookies.accept') }}</button>
        </div>
    </div>
@endif
