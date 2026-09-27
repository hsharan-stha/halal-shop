<x-layouts.shop :title="__('shop.auth.reset_password')" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-8 sm:py-12">
        <div class="card p-6 sm:p-8">
            <h1 class="text-2xl font-bold">{{ __('shop.auth.reset_password') }}</h1>

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-ui.input name="email" type="email" :label="__('shop.fields.email')" :value="$email" required autocomplete="email" />
                <x-ui.input name="password" type="password" :label="__('shop.fields.new_password')" required autocomplete="new-password" />
                <x-ui.input name="password_confirmation" type="password" :label="__('shop.fields.password_confirmation')" required autocomplete="new-password" />
                <x-ui.button class="w-full">{{ __('shop.auth.reset_password') }}</x-ui.button>
            </form>
        </div>
    </div>
</x-layouts.shop>
