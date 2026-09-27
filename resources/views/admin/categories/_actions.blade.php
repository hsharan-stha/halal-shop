@can('categories.manage')
    <div class="inline-flex shrink-0 items-center gap-1">
        <x-ui.button variant="ghost" size="sm" :href="route('admin.categories.create', ['parent' => $category->id])" icon="plus" :title="__('admin.categories.add_child')" :aria-label="__('admin.categories.add_child')" />
        <x-ui.button variant="ghost" size="sm" :href="route('admin.categories.edit', $category)" icon="pencil" :aria-label="__('admin.edit')" />
        <x-ui.confirm-form :action="route('admin.categories.destroy', $category)" method="DELETE" :message="__('admin.categories.confirm_delete')">
            <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
        </x-ui.confirm-form>
    </div>
@endcan
