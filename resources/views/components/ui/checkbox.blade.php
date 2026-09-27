@props(['name', 'label', 'checked' => false, 'value' => '1', 'id' => null, 'hint' => null])

@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name).(str_ends_with($name, '[]') ? '-'.$value : '');
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $isArray = str_ends_with($name, '[]');
    $isChecked = $isArray
        ? in_array((string) $value, array_map('strval', (array) old(rtrim($errorKey, '.'), $checked ? [$value] : [])), true)
        : (bool) old($errorKey, $checked);
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-1.5">
        @unless ($isArray)
            <input type="hidden" name="{{ $name }}" value="0">
        @endunless
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) class="form-check mt-0.5" {{ $attributes->except('class') }}>
        <span class="text-sm">
            <span class="text-ink">{{ $label }}</span>
            @if ($hint)<span class="block text-xs text-ink-muted">{{ $hint }}</span>@endif
        </span>
    </label>
    @error($errorKey)<p class="form-error">{{ $message }}</p>@enderror
</div>
