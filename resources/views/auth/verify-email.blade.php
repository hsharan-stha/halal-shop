<x-layouts.shop :title="__('shop.auth.verify_email')" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-8 sm:py-12">
        <div class="card p-6 text-center sm:p-8">
            <div class="mx-auto mb-4 grid size-14 place-items-center rounded-2xl bg-primary-soft text-primary"><x-icon name="mail" class="size-7" /></div>
            <h1 class="text-xl font-bold">{{ __('shop.auth.verify_email') }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ __('shop.auth.verify_email_help', ['email' => auth()->user()->email]) }}</p>

            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <x-ui.button class="w-full">{{ __('shop.auth.resend_verification') }}</x-ui.button>
            </form>
            <a href="{{ route('home') }}" class="btn btn-ghost mt-2 w-full">{{ __('shop.auth.continue_shopping') }}</a>
        </div>
    </div>
</x-layouts.shop>
