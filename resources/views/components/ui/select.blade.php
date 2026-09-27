@props(['name', 'label' => null, 'options' => [], 'value' => null, 'hint' => null, 'required' => false, 'id' => null, 'placeholder' => null, 'wrapperClass' => ''])

@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $selected = (string) old($errorKey, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$required" :class="$wrapperClass">
    <select id="{{ $id }}" name="{{ $name }}"
        @if ($required) required @endif
        @error($errorKey) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge(['class' => 'form-control pr-8']) }}>
        @if (! is_null($placeholder))
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-ui.field>
