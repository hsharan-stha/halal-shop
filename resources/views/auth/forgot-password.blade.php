<x-layouts.shop :title="__('shop.auth.forgot_password')" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-8 sm:py-12">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('shop.auth.forgot_password') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">{{ __('shop.auth.forgot_password_help') }}</p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.input name="email" type="email" :label="__('shop.fields.email')" required autocomplete="email" inputmode="email" autofocus />
                <x-ui.button class="w-full">{{ __('shop.auth.send_reset_link') }}</x-ui.button>
            </form>

            <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="text-primary hover:underline">{{ __('shop.auth.back_to_login') }}</a></p>
        </div>
    </div>
</x-layouts.shop>
