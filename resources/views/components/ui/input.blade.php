@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null, 'wrapperClass' => ''])

@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? null : old($errorKey, $value);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$required" :class="$wrapperClass">
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if (! is_null($current)) value="{{ $current }}" @endif
        @if ($required) required @endif
        @error($errorKey) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        @if ($hint && ! $errors->has($errorKey)) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->merge(['class' => 'form-control']) }}>
</x-ui.field>
