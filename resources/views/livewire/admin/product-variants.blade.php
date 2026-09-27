<div>
    <x-ui.card :title="__('admin.variants.title')" :description="settings('tax.prices_include_tax') ? __('admin.products.prices_include_tax') : __('admin.products.prices_exclude_tax')" padding="p-0">
        <x-slot:actions>
            @unless ($showForm)
                <x-ui.button type="button" size="sm" icon="plus" wire:click="create">{{ __('admin.variants.add') }}</x-ui.button>
            @endunless
        </x-slot:actions>

        <ul class="divide-y divide-line" wire:sort="reorder">
            @foreach ($variants as $variant)
                <li wire:key="variant-{{ $variant->id }}" wire:sort:item="{{ $variant->id }}" class="flex items-center gap-3 px-4 py-3 sm:px-6">
                    <button type="button" wire:sort:handle class="cursor-grab text-ink-muted hover:text-ink" aria-label="{{ __('admin.drag_to_reorder') }}"><x-icon name="menu" class="size-5" /></button>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            {{ $variant->label() }}
                            @if ($variant->is_default)<x-ui.badge color="primary">{{ __('admin.variants.default') }}</x-ui.badge>@endif
                            @unless ($variant->is_active)<x-ui.badge>{{ __('admin.inactive') }}</x-ui.badge>@endunless
                        </p>
                        <p class="text-xs text-ink-muted">
                            <span class="font-mono">{{ $variant->sku }}</span>
                            @if ($variant->barcode) · JAN {{ $variant->barcode }}@endif
                            @if ($variant->weight_grams) · {{ number_format($variant->weight_grams) }}g @endif
                        </p>
                    </div>
                    <div class="text-right tabular-nums">
                        <p class="font-semibold">{{ money($variant->price) }}</p>
                        @if ($variant->isOnSale())<p class="text-xs text-ink-muted line-through">{{ money($variant->compare_at_price) }}</p>@endif
                    </div>
                    <div class="flex shrink-0 items-center gap-1" wire:sort:ignore>
                        @unless ($variant->is_default)
                            <x-ui.button type="button" variant="ghost" size="sm" icon="star" wire:click="setDefault({{ $variant->id }})" :aria-label="__('admin.variants.make_default')" :title="__('admin.variants.make_default')" />
                        @endunless
                        <x-ui.button type="button" variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $variant->id }})" :aria-label="__('admin.edit')" />
                        @unless ($variant->is_default)
                            <x-ui.button type="button" variant="ghost" size="sm" icon="trash" class="text-danger" wire:click="delete({{ $variant->id }})" wire:confirm="{{ __('admin.variants.confirm_delete') }}" :aria-label="__('admin.delete')" />
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($showForm)
            <form wire:submit="save" class="grid gap-4 border-t border-line bg-surface-muted/30 p-4 sm:p-6 md:grid-cols-3">
                <p class="font-semibold md:col-span-3">{{ $editingId ? __('admin.variants.edit') : __('admin.variants.add') }}</p>
                <x-ui.input name="name_ja" id="variant-name_ja" :label="__('admin.variants.name').' (日本語)'" wire:model="name_ja" :placeholder="__('admin.variants.name_placeholder')" lang="ja" />
                <x-ui.input name="name_en" id="variant-name_en" :label="__('admin.variants.name').' (English)'" wire:model="name_en" lang="en" />
                <x-ui.input name="sku" id="variant-sku" :label="__('admin.variants.sku')" wire:model="sku" required class="font-mono uppercase" />
                <x-ui.input name="price" id="variant-price" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.price')" wire:model="price" required />
                <x-ui.input name="compare_at_price" id="variant-compare_at_price" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.compare_at_price')" wire:model="compare_at_price" />
                <x-ui.input name="cost_price" id="variant-cost_price" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.cost_price')" wire:model="cost_price" />
                <x-ui.input name="barcode" id="variant-barcode" inputmode="numeric" :label="__('admin.variants.barcode')" wire:model="barcode" maxlength="14" />
                <x-ui.input name="weight_grams" id="variant-weight_grams" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.weight_grams')" wire:model="weight_grams" />
                <div class="flex items-end">
                    <label class="flex min-h-11 items-center gap-3"><input type="checkbox" wire:model="is_active" class="form-check"><span class="text-sm">{{ __('admin.active') }}</span></label>
                </div>
                @error('is_active')<p class="form-error md:col-span-3">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2 md:col-span-3">
                    <x-ui.button type="button" variant="secondary" wire:click="cancel">{{ __('shop.cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('shop.save') }}</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.card>
</div>
