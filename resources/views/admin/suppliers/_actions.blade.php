@can('suppliers.manage')
    <div class="inline-flex shrink-0 items-center gap-1">
        <x-ui.button variant="ghost" size="sm" :href="route('admin.suppliers.edit', $supplier)" icon="pencil" :aria-label="__('admin.edit')" />
        <x-ui.confirm-form :action="route('admin.suppliers.destroy', $supplier)" method="DELETE" :message="__('admin.suppliers.confirm_delete')">
            <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
        </x-ui.confirm-form>
    </div>
@endcan
