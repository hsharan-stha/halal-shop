<x-layouts.account :title="__('shop.account.nav.settings')">
    <x-ui.card>
        <form method="POST" action="{{ route('account.settings.update') }}" class="grid max-w-lg gap-4"
              x-data @submit="localStorage.setItem('theme', $el.querySelector('[name=theme]').value)">
            @csrf
            @method('PUT')
            <x-ui.select name="locale" :label="__('shop.fields.language')" :options="collect(app(\App\Services\LocaleService::class)->enabled())->map(fn ($l) => $l['native'])->all()" :value="$user->locale" required />
            @if ($branding->darkModeEnabled())
                <x-ui.select name="theme" :label="__('shop.theme.label')" :options="['system' => __('shop.theme.system'), 'light' => __('shop.theme.light'), 'dark' => __('shop.theme.dark')]" :value="$user->settings->theme ?? 'system'" required />
            @else
                <input type="hidden" name="theme" value="system">
            @endif
            <x-ui.checkbox name="marketing_emails" :label="__('shop.account.marketing_emails')" :hint="__('shop.account.marketing_emails_hint')" :checked="(bool) $user->settings->marketing_emails" />
            @if (Route::has('account.notifications.preferences'))
                <a href="{{ route('account.notifications.preferences') }}" class="text-sm text-primary hover:underline">{{ __('shop.account.notification_preferences') }}</a>
            @endif
            <div><x-ui.button>{{ __('shop.save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.account>
