@php($editing = $member->exists)

<x-layouts.admin :title="$editing ? __('admin.staff.edit') : __('admin.staff.create')">
    <x-ui.page-header :title="$editing ? __('admin.staff.edit') : __('admin.staff.create')" :back="route('admin.staff.index')" />

    <x-ui.card class="max-w-3xl">
        <form method="POST" action="{{ $editing ? route('admin.staff.update', $member) : route('admin.staff.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-ui.input name="name" :label="__('shop.fields.name')" :value="$member->name" required />
            <x-ui.input name="email" type="email" :label="__('shop.fields.email')" :value="$member->email" required autocomplete="off" />
            <x-ui.input name="password" type="password" :label="$editing ? __('admin.staff.new_password_optional') : __('shop.fields.password')" :required="! $editing" autocomplete="new-password" />
            <x-ui.input name="password_confirmation" type="password" :label="__('shop.fields.password_confirmation')" :required="! $editing" autocomplete="new-password" />

            <fieldset class="md:col-span-2">
                <legend class="form-label">{{ __('admin.roles.title') }}</legend>
                @if ($editing && $member->is(auth()->user()))
                    <p class="form-hint mb-2">{{ __('admin.staff.cannot_change_own_roles') }}</p>
                @endif
                <div class="grid gap-x-4 sm:grid-cols-2">
                    @foreach ($roles as $role)
                        <x-ui.checkbox name="roles[]" :value="$role->slug" :label="$role->label()" :checked="$member->exists && $member->roles->contains('slug', $role->slug)" />
                    @endforeach
                </div>
                @error('roles')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>

            <div class="flex justify-end border-t border-line pt-4 md:col-span-2">
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.admin>
