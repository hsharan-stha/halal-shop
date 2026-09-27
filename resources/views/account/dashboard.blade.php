<x-layouts.account :title="__('shop.account.nav.dashboard')">
    @unless ($user->hasVerifiedEmail())
        <x-ui.alert type="warning" class="mb-4">
            {{ __('shop.auth.verify_email_banner') }}
            <form method="POST" action="{{ route('verification.send') }}" class="mt-2">
                @csrf
                <button type="submit" class="font-semibold underline">{{ __('shop.auth.resend_verification') }}</button>
            </form>
        </x-ui.alert>
    @endunless

    <p class="mb-6 text-ink-muted">{{ __('shop.account.welcome', ['name' => $user->name]) }}</p>

    @isset($recentOrders)
        {{ $recentOrders }}
    @endisset

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach (\App\Support\Navigation::account() as $item)
            @continue($item['route'] === 'account.dashboard')
            <a href="{{ route($item['route']) }}" class="card flex min-h-20 items-center gap-4 p-4 hover:border-primary">
                <span class="grid size-11 place-items-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$item['icon']" /></span>
                <span class="font-semibold">{{ __($item['label']) }}</span>
                <x-icon name="chevron-right" class="ml-auto text-ink-muted" />
            </a>
        @endforeach
    </div>
</x-layouts.account>
