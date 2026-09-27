<x-layouts.admin :title="__('admin.roles.edit_title', ['role' => $role->label()])">
    <x-ui.page-header :title="__('admin.roles.edit_title', ['role' => $role->label()])" :back="route('admin.roles.index')" />

    @php($granted = $role->permissions->pluck('slug')->all())

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf
        @method('PUT')
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $group => $permissions)
                <x-ui.card :title="__('admin.permissions.groups.'.$group)" padding="p-4">
                    @foreach ($permissions as $permission)
                        <x-ui.checkbox name="permissions[]" :value="$permission" :label="\App\Support\PermissionCatalog::label($permission)" :checked="in_array($permission, $granted, true)" />
                    @endforeach
                </x-ui.card>
            @endforeach
        </div>
        <div class="sticky bottom-0 mt-6 -mx-3 flex justify-end border-t border-line bg-surface/95 px-3 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border">
            <x-ui.button>{{ __('shop.save') }}</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
