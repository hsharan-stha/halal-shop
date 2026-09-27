<x-layouts.account :title="__('shop.account.nav.security')">
    <div class="space-y-6">
        <x-ui.card :title="__('shop.account.change_password')">
            <form method="POST" action="{{ route('account.password.update') }}" class="grid max-w-lg gap-4">
                @csrf
                @method('PUT')
                <x-ui.input name="current_password" type="password" :label="__('shop.fields.current_password')" required autocomplete="current-password" />
                <x-ui.input name="password" type="password" :label="__('shop.fields.new_password')" required autocomplete="new-password" />
                <x-ui.input name="password_confirmation" type="password" :label="__('shop.fields.password_confirmation')" required autocomplete="new-password" />
                <div><x-ui.button>{{ __('shop.account.update_password') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('shop.account.sessions')" :description="__('shop.account.sessions_help')">
            @if ($sessions->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('shop.account.sessions_unavailable') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($sessions as $session)
                        <li class="flex items-center gap-3 py-3">
                            <x-icon name="globe" class="text-ink-muted" />
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-medium">{{ $session->agent }} @if ($session->is_current)<x-ui.badge color="success">{{ __('shop.account.this_device') }}</x-ui.badge>@endif</p>
                                <p class="text-xs text-ink-muted">{{ $session->ip_address }} · {{ local_date($session->last_active, true) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($sessions->count() > 1)
                    <form method="POST" action="{{ route('account.sessions.destroy') }}" class="mt-4 flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:items-end">
                        @csrf
                        @method('DELETE')
                        <x-ui.input name="password" type="password" :label="__('shop.fields.current_password')" required autocomplete="current-password" wrapper-class="sm:w-64" id="sessions-password" />
                        <x-ui.button variant="secondary">{{ __('shop.account.logout_other_sessions') }}</x-ui.button>
                    </form>
                @endif
            @endif
        </x-ui.card>

        @if ($tokens->isNotEmpty())
            <x-ui.card :title="__('shop.account.api_tokens')" :description="__('shop.account.api_tokens_help')">
                <ul class="divide-y divide-line">
                    @foreach ($tokens as $token)
                        <li class="flex items-center gap-3 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $token->name }}</p>
                                <p class="text-xs text-ink-muted">{{ __('shop.account.last_used') }}: {{ $token->last_used_at ? local_date($token->last_used_at, true) : '—' }}</p>
                            </div>
                            <x-ui.confirm-form :action="route('account.tokens.destroy', $token->id)" method="DELETE">
                                <x-ui.button variant="ghost" size="sm">{{ __('shop.account.revoke') }}</x-ui.button>
                            </x-ui.confirm-form>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</x-layouts.account>
