@can('staff.manage')
    @if (! $member->isSuperAdmin() || auth()->user()->isSuperAdmin())
        <div class="flex flex-wrap justify-end gap-2">
            <x-ui.button :href="route('admin.staff.edit', $member)" variant="secondary" size="sm" icon="pencil">{{ __('admin.edit') }}</x-ui.button>
            @if ($member->isNot(auth()->user()))
                <x-ui.confirm-form :action="route('admin.staff.toggle-status', $member)" :message="__('admin.staff.confirm_toggle')">
                    <x-ui.button :variant="$member->isActive() ? 'ghost' : 'secondary'" size="sm">{{ $member->isActive() ? __('admin.suspend') : __('admin.reactivate') }}</x-ui.button>
                </x-ui.confirm-form>
            @endif
        </div>
    @endif
@endcan
