<x-layouts.auth :title="__('admin.login.title')">
    <h1 class="text-xl font-bold">{{ __('admin.login.title') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('admin.login.subtitle') }}</p>

    <form method="POST" action="{{ route('admin.login') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input name="email" type="email" :label="__('shop.fields.email')" required autocomplete="username" autofocus />
        <x-ui.input name="password" type="password" :label="__('shop.fields.password')" required autocomplete="current-password" />
        <x-ui.checkbox name="remember" :label="__('shop.auth.remember_me')" />
        <x-ui.button class="w-full">{{ __('shop.auth.login') }}</x-ui.button>
    </form>
</x-layouts.auth>
