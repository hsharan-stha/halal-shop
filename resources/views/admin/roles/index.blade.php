<x-layouts.admin :title="__('admin.nav.roles')">
    <x-ui.page-header :title="__('admin.roles.title')" :description="__('admin.roles.description')" />

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.roles.role') }}</th>
            <th>{{ __('admin.roles.users') }}</th>
            <th>{{ __('admin.roles.permissions') }}</th>
            <th class="text-right">{{ __('admin.actions') }}</th>
        </x-slot:head>
        @foreach ($roles as $role)
            <tr>
                <td class="font-medium">{{ $role->label() }}</td>
                <td class="tabular-nums">{{ $role->users_count }}</td>
                <td class="tabular-nums">{{ $role->slug === 'super_admin' ? __('admin.roles.all') : $role->permissions_count }}</td>
                <td class="text-right">
                    @if (! in_array($role->slug, ['super_admin', 'customer']) && auth()->user()->can('roles.manage'))
                        <x-ui.button :href="route('admin.roles.edit', $role)" variant="secondary" size="sm" icon="pencil">{{ __('admin.edit') }}</x-ui.button>
                    @endif
                </td>
            </tr>
        @endforeach
        <x-slot:mobile>
            @foreach ($roles as $role)
                <div class="flex items-center gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $role->label() }}</p>
                        <p class="text-xs text-ink-muted">{{ __('admin.roles.users') }}: {{ $role->users_count }} · {{ __('admin.roles.permissions') }}: {{ $role->slug === 'super_admin' ? __('admin.roles.all') : $role->permissions_count }}</p>
                    </div>
                    @if (! in_array($role->slug, ['super_admin', 'customer']) && auth()->user()->can('roles.manage'))
                        <x-ui.button :href="route('admin.roles.edit', $role)" variant="secondary" size="sm">{{ __('admin.edit') }}</x-ui.button>
                    @endif
                </div>
            @endforeach
        </x-slot:mobile>
    </x-ui.table>
</x-layouts.admin>
