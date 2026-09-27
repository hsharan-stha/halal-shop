<div @if ($processing) wire:poll.5s @endif>
    <x-ui.card :title="__('admin.images.title')" :description="__('admin.images.reorder_hint')">
        <div class="space-y-4">
            <label for="product-image-upload" class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line bg-surface-muted/40 px-4 py-4 text-center text-sm text-ink-muted hover:border-primary">
                <x-icon name="upload" class="size-6" />
                <span wire:loading.remove wire:target="uploads">{{ __('admin.images.upload') }}</span>
                <span wire:loading wire:target="uploads">{{ __('admin.images.uploading') }}</span>
                <span class="text-xs">{{ __('admin.images.hint') }}</span>
            </label>
            <input id="product-image-upload" type="file" wire:model="uploads" multiple accept="image/jpeg,image/png,image/webp" class="sr-only">
            @error('uploads')<p class="form-error">{{ $message }}</p>@enderror
            @error('uploads.*')<p class="form-error">{{ $message }}</p>@enderror

            @if ($images->isEmpty())
                <p class="text-center text-sm text-ink-muted">{{ __('admin.images.empty') }}</p>
            @else
                <ul class="grid gap-3 sm:grid-cols-2" wire:sort="reorder">
                    @foreach ($images as $image)
                        <li wire:key="image-{{ $image->id }}" wire:sort:item="{{ $image->id }}" class="flex gap-3 rounded-xl border border-line bg-surface p-3">
                            <div class="relative shrink-0" wire:sort:handle>
                                <img src="{{ $image->url('small') }}" alt="{{ $image->altText($product) }}" class="size-24 cursor-grab rounded-lg border border-line bg-white object-contain" loading="lazy">
                                @if ($loop->first)<span class="absolute top-1 left-1"><x-ui.badge color="primary">{{ __('admin.images.primary') }}</x-ui.badge></span>@endif
                                @unless ($image->isProcessed())<span class="absolute right-1 bottom-1"><x-ui.badge color="warning">{{ __('admin.images.processing') }}</x-ui.badge></span>@endunless
                            </div>
                            <form wire:submit="saveMeta({{ $image->id }})" class="min-w-0 flex-1 space-y-2" wire:sort:ignore>
                                <label class="sr-only" for="alt-ja-{{ $image->id }}">{{ __('admin.images.alt') }} (日本語)</label>
                                <input id="alt-ja-{{ $image->id }}" type="text" wire:model="meta.{{ $image->id }}.ja" placeholder="{{ __('admin.images.alt') }}（日本語）" class="form-control min-h-9 py-1 text-sm" maxlength="200" lang="ja">
                                <label class="sr-only" for="alt-en-{{ $image->id }}">{{ __('admin.images.alt') }} (English)</label>
                                <input id="alt-en-{{ $image->id }}" type="text" wire:model="meta.{{ $image->id }}.en" placeholder="{{ __('admin.images.alt') }} (English)" class="form-control min-h-9 py-1 text-sm" maxlength="200" lang="en">
                                @if (count($variants) > 1)
                                    <label class="sr-only" for="variant-{{ $image->id }}">{{ __('admin.images.variant') }}</label>
                                    <select id="variant-{{ $image->id }}" wire:model="meta.{{ $image->id }}.variant" class="form-control min-h-9 py-1 text-sm">
                                        <option value="">{{ __('admin.images.all_variants') }}</option>
                                        @foreach ($variants as $variantId => $variantLabel)
                                            <option value="{{ $variantId }}">{{ $variantLabel }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <div class="flex justify-end gap-1">
                                    <x-ui.button size="sm" variant="secondary">{{ __('shop.save') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" variant="ghost" icon="trash" class="text-danger" wire:click="delete({{ $image->id }})" wire:confirm="{{ __('admin.images.confirm_delete') }}" :aria-label="__('admin.delete')" />
                                </div>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-ui.card>
</div>
