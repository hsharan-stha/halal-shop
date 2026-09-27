<x-layouts.admin :title="__('admin.nav.staff')">
    <x-ui.page-header :title="__('admin.staff.title')">
        <x-slot:actions>
            @can('staff.manage')
                <x-ui.button :href="route('admin.staff.create')" icon="plus">{{ __('admin.staff.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex gap-2">
        <label for="staff-q" class="sr-only">{{ __('admin.search') }}</label>
        <input id="staff-q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.search_placeholder') }}" class="form-control max-w-sm">
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
    </form>

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('shop.fields.name') }}</th>
            <th>{{ __('admin.roles.role') }}</th>
            <th>{{ __('admin.status') }}</th>
            <th>{{ __('admin.staff.last_login') }}</th>
            <th class="text-right">{{ __('admin.actions') }}</th>
        </x-slot:head>
        @foreach ($staff as $member)
            <tr>
                <td>
                    <p class="font-medium">{{ $member->name }}</p>
                    <p class="text-xs text-ink-muted">{{ $member->email }}</p>
                </td>
                <td><div class="flex flex-wrap gap-1">@foreach ($member->roles as $role)<x-ui.badge color="primary">{{ $role->label() }}</x-ui.badge>@endforeach</div></td>
                <td><x-ui.badge :color="$member->status->color()">{{ $member->status->label() }}</x-ui.badge></td>
                <td class="text-sm text-ink-muted">{{ $member->last_login_at ? local_date($member->last_login_at, true) : '—' }}</td>
                <td class="text-right">@include('admin.staff._actions')</td>
            </tr>
        @endforeach
        <x-slot:mobile>
            @foreach ($staff as $member)
                <div class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $member->name }}</p>
                            <p class="truncate text-xs text-ink-muted">{{ $member->email }}</p>
                        </div>
                        <x-ui.badge :color="$member->status->color()">{{ $member->status->label() }}</x-ui.badge>
                    </div>
                    <div class="flex flex-wrap gap-1">@foreach ($member->roles as $role)<x-ui.badge color="primary">{{ $role->label() }}</x-ui.badge>@endforeach</div>
                    @include('admin.staff._actions')
                </div>
            @endforeach
        </x-slot:mobile>
    </x-ui.table>

    <div class="mt-4">{{ $staff->links() }}</div>
</x-layouts.admin>
