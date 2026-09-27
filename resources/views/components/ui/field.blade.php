{{-- Wrapper providing label, hint and error for a form control. --}}
@props(['label' => null, 'for' => null, 'name' => null, 'hint' => null, 'required' => false])

@php($errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null)

<div {{ $attributes }}>
    @if ($label)
        <label for="{{ $for }}" class="form-label">
            {{ $label }}
            @if ($required)<span class="text-danger" aria-hidden="true">*</span><span class="sr-only">({{ __('shop.required') }})</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($hint)
        <p class="form-hint" id="{{ $for }}-hint">{{ $hint }}</p>
    @endif
    @if ($errorKey)
        @error($errorKey)
            <p class="form-error" id="{{ $for }}-error">{{ $message }}</p>
        @enderror
    @endif
</div>
