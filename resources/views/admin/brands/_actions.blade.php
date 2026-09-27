@can('brands.manage')
    @if ($brand->isManageableByCurrentUser())
        <div class="inline-flex shrink-0 items-center gap-1">
            <x-ui.button variant="ghost" size="sm" :href="route('admin.brands.edit', $brand)" icon="pencil" :aria-label="__('admin.edit')" />
            <x-ui.confirm-form :action="route('admin.brands.destroy', $brand)" method="DELETE" :message="__('admin.brands.confirm_delete')">
                <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
            </x-ui.confirm-form>
        </div>
    @endif
@endcan
