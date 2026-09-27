{{-- Paired inputs for a {"ja": ..., "en": ...} attribute. --}}
@props(['name', 'label', 'value' => null, 'textarea' => false, 'rows' => 3, 'hint' => null, 'required' => false])

<fieldset {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <legend class="form-label">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </legend>
    <div class="grid gap-3 md:grid-cols-2">
        @foreach (config('shop.locales') as $locale => $meta)
            @if ($textarea)
                <x-ui.textarea :name="$name.'['.$locale.']'" :label="$meta['native']" :value="$value[$locale] ?? null" :rows="$rows" :required="$required && $locale === 'ja'" :lang="$locale" />
            @else
                <x-ui.input :name="$name.'['.$locale.']'" :label="$meta['native']" :value="$value[$locale] ?? null" :required="$required && $locale === 'ja'" :lang="$locale" />
            @endif
        @endforeach
    </div>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
</fieldset>
