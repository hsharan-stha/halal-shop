@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'required' => false, 'id' => null, 'rows' => 4, 'wrapperClass' => ''])

@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$required" :class="$wrapperClass">
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($required) required @endif
        @error($errorKey) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge(['class' => 'form-control']) }}>{{ old($errorKey, $value) }}</textarea>
</x-ui.field>
