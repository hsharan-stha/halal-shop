<x-layouts.shop :title="__('shop.auth.login')" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-8 sm:py-12">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('shop.auth.login') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('shop.auth.login_subtitle') }}</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.input name="email" type="email" :label="__('shop.fields.email')" required autocomplete="username" inputmode="email" autofocus />
                <x-ui.input name="password" type="password" :label="__('shop.fields.password')" required autocomplete="current-password" />
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-ui.checkbox name="remember" :label="__('shop.auth.remember_me')" />
                    <a href="{{ route('password.request') }}" class="text-sm text-primary hover:underline">{{ __('shop.auth.forgot_password') }}</a>
                </div>
                <x-ui.button class="w-full">{{ __('shop.auth.login') }}</x-ui.button>
            </form>

            @feature('registration_enabled')
                <p class="mt-6 text-center text-sm text-ink-muted">
                    {{ __('shop.auth.no_account') }}
                    <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">{{ __('shop.auth.register') }}</a>
                </p>
            @endfeature
        </div>
    </div>
</x-layouts.shop>
