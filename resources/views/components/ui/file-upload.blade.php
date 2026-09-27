@props(['name', 'label' => null, 'accept' => 'image/jpeg,image/png,image/webp', 'hint' => null, 'current' => null, 'removable' => false, 'multiple' => false, 'id' => null])

@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
@endphp

<x-ui.field :label="$label" :for="$id" :name="$errorKey" :hint="$hint" :class="$attributes->get('class')">
    <div x-data="{ files: [] }" class="space-y-3">
        @if ($current)
            <div class="flex items-center gap-3">
                <img src="{{ $current }}" alt="" class="size-16 rounded-lg border border-line bg-surface-muted object-contain">
                @if ($removable)
                    <x-ui.checkbox :name="rtrim($name, '[]').'_remove'" :label="__('shop.remove')" />
                @endif
            </div>
        @endif
        <label for="{{ $id }}" class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-line bg-surface-muted/40 px-4 py-4 text-center text-sm text-ink-muted hover:border-primary">
            <x-icon name="upload" class="size-6" />
            <span x-show="files.length === 0">{{ __('shop.upload.choose') }}</span>
            <span x-show="files.length > 0" x-text="files.join(', ')" class="break-all text-ink"></span>
        </label>
        <input id="{{ $id }}" type="file" name="{{ $name }}" accept="{{ $accept }}" class="sr-only" @if ($multiple) multiple @endif
               @change="files = Array.from($event.target.files).map(f => f.name)" {{ $attributes->except('class') }}>
    </div>
</x-ui.field>
