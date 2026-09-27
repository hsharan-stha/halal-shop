<x-layouts.account :title="__('shop.account.nav.privacy')">
    <div class="space-y-6">
        <x-ui.card :title="__('shop.privacy.your_data')" :description="__('shop.privacy.your_data_help')">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                @foreach ($data['account'] as $key => $value)
                    <div>
                        <dt class="text-ink-muted">{{ __('shop.privacy.fields.'.$key) }}</dt>
                        <dd class="font-medium break-all">{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button :href="route('account.privacy.export')" variant="secondary" icon="download">{{ __('shop.privacy.export') }}</x-ui.button>
                <x-ui.button :href="route('account.profile')" variant="ghost" icon="pencil">{{ __('shop.privacy.edit_profile') }}</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('shop.privacy.deactivate')" :description="__('shop.privacy.deactivate_help')">
            <form method="POST" action="{{ route('account.privacy.deactivate') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end" x-data="confirmSubmit(@js(__('shop.privacy.deactivate_confirm')))" @submit="submit($event)">
                @csrf
                <x-ui.input name="password" type="password" :label="__('shop.fields.current_password')" required autocomplete="current-password" id="deactivate-password" wrapper-class="sm:w-64" />
                <x-ui.button variant="secondary">{{ __('shop.privacy.deactivate') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('shop.privacy.delete')" class="border-danger/40">
            <p class="mb-4 text-sm text-ink-muted">{{ __('shop.privacy.delete_help') }}</p>
            <form method="POST" action="{{ route('account.privacy.destroy') }}" class="space-y-3" x-data="confirmSubmit(@js(__('shop.privacy.delete_confirm')))" @submit="submit($event)">
                @csrf
                @method('DELETE')
                <x-ui.input name="password" type="password" :label="__('shop.fields.current_password')" required autocomplete="current-password" id="delete-password" wrapper-class="sm:w-64" />
                <x-ui.checkbox name="confirmation" :label="__('shop.privacy.delete_acknowledge')" />
                <x-ui.button variant="danger" icon="trash">{{ __('shop.privacy.delete') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.account>
