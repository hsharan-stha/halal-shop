<div class="inline-flex flex-wrap items-center gap-1">
    @if ($product->trashed())
        @can('products.delete')
            <form method="POST" action="{{ route('admin.products.restore', $product->id) }}">
                @csrf
                <x-ui.button variant="secondary" size="sm" icon="refresh">{{ __('admin.products.restore') }}</x-ui.button>
            </form>
        @endcan
    @else
        @can('products.update')
            <x-ui.button variant="ghost" size="sm" :href="route('admin.products.edit', $product)" icon="pencil" :aria-label="__('admin.edit')" />
        @endcan
        @can('products.create')
            <form method="POST" action="{{ route('admin.products.duplicate', $product) }}">
                @csrf
                <x-ui.button variant="ghost" size="sm" icon="copy" :aria-label="__('admin.products.duplicate')" :title="__('admin.products.duplicate')" />
            </form>
        @endcan
        @can('products.delete')
            <x-ui.confirm-form :action="route('admin.products.destroy', $product)" method="DELETE" :message="__('admin.products.confirm_delete')">
                <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
            </x-ui.confirm-form>
        @endcan
    @endif
</div>
