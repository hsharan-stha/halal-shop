<x-layouts.shop :title="__('shop.auth.register')" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-8 sm:py-12">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('shop.auth.register') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('shop.auth.register_subtitle') }}</p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.input name="name" :label="__('shop.fields.name')" required autocomplete="name" maxlength="100" />
                <x-ui.input name="email" type="email" :label="__('shop.fields.email')" required autocomplete="email" inputmode="email" />
                <x-ui.input name="phone" type="tel" :label="__('shop.fields.phone_optional')" autocomplete="tel" inputmode="tel" :hint="__('shop.hints.phone')" />
                <x-ui.input name="password" type="password" :label="__('shop.fields.password')" required autocomplete="new-password" :hint="__('shop.hints.password', ['min' => max(8, (int) settings('security.password_min_length'))])" />
                <x-ui.input name="password_confirmation" type="password" :label="__('shop.fields.password_confirmation')" required autocomplete="new-password" />

                <div>
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 py-1.5">
                        <input type="checkbox" name="terms" value="1" class="form-check mt-0.5" @checked(old('terms')) required>
                        <span class="text-sm">
                            @if (Route::has('pages.show'))
                                {!! __('shop.auth.terms_agree_links', [
                                    'terms' => '<a href="'.e(route('pages.show', 'terms')).'" class="text-primary underline" target="_blank">'.e(__('shop.footer.terms')).'</a>',
                                    'privacy' => '<a href="'.e(route('pages.show', 'privacy')).'" class="text-primary underline" target="_blank">'.e(__('shop.footer.privacy')).'</a>',
                                ]) !!}
                            @else
                                {{ __('shop.auth.terms_agree') }}
                            @endif
                        </span>
                    </label>
                    @error('terms')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <x-ui.button class="w-full">{{ __('shop.auth.create_account') }}</x-ui.button>
            </form>

            <p class="mt-6 text-center text-sm text-ink-muted">
                {{ __('shop.auth.have_account') }}
                <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('shop.auth.login') }}</a>
            </p>
        </div>
    </div>
</x-layouts.shop>
