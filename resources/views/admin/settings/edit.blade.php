<x-layouts.admin :title="__('admin.nav.settings')">
    <x-ui.page-header :title="__('admin.settings.title')" :description="__('admin.settings.groups.'.$group.'.description')" />

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <nav aria-label="{{ __('admin.settings.title') }}" class="scrollbar-none -mx-3 overflow-x-auto px-3 lg:mx-0 lg:px-0">
            <ul class="flex gap-2 lg:card lg:flex-col lg:gap-0 lg:p-2">
                @foreach ($groups as $item)
                    <li class="shrink-0">
                        <a href="{{ route('admin.settings.edit', $item) }}" @class([
                            'flex min-h-10 items-center rounded-full border px-3.5 text-sm whitespace-nowrap lg:rounded-lg lg:border-0',
                            'border-primary bg-primary-soft font-semibold text-primary' => $item === $group,
                            'border-line bg-surface text-ink-muted hover:text-ink lg:bg-transparent lg:hover:bg-surface-muted' => $item !== $group,
                        ])>{{ __('admin.settings.groups.'.$item.'.title') }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <x-ui.card :title="__('admin.settings.groups.'.$group.'.title')">
            <form method="POST" action="{{ route('admin.settings.update', $group) }}" enctype="multipart/form-data" class="grid gap-5 md:grid-cols-2">
                @csrf
                @method('PUT')

                @foreach ($fields as $key => $field)
                    @php
                        $label = __('admin.settings.fields.'.$group.'.'.$key);
                        $hintKey = 'admin.settings.hints.'.$group.'.'.$key;
                        $hint = Lang::has($hintKey) ? __($hintKey) : null;
                        $value = $values[$key];
                    @endphp

                    @switch($field['type'])
                        @case('boolean')
                            <x-ui.checkbox :name="$key" :label="$label" :hint="$hint" :checked="(bool) $value" class="md:col-span-2" />
                            @break
                        @case('color')
                            <x-ui.field :label="$label" :for="'f-'.$key" :name="$key" :hint="$hint">
                                <div class="flex items-center gap-2" x-data="{ c: @js(old($key, $value)) }">
                                    <input type="color" x-model="c" class="h-11 w-14 cursor-pointer rounded-lg border border-line bg-surface p-1" aria-label="{{ $label }}">
                                    <input id="f-{{ $key }}" name="{{ $key }}" type="text" x-model="c" class="form-control font-mono" pattern="#[0-9a-fA-F]{6}" maxlength="7" required>
                                </div>
                            </x-ui.field>
                            @break
                        @case('image')
                            <x-ui.file-upload :name="$key" :label="$label" :hint="$hint" :current="settings()->mediaUrl($value)" :removable="true" accept="image/png,image/jpeg,image/webp,image/x-icon" />
                            @break
                        @case('select')
                            <x-ui.select :name="$key" :label="$label" :hint="$hint" :options="$field['options']" :value="$value" required />
                            @break
                        @case('integer')
                            <x-ui.input :name="$key" type="number" :label="$label" :hint="$hint" :value="$value" required inputmode="numeric" />
                            @break
                        @case('int_list')
                            <x-ui.input :name="$key" :label="$label" :hint="$hint ?? __('admin.settings.int_list_hint')" :value="implode(', ', (array) $value)" inputmode="numeric" />
                            @break
                        @case('text')
                            <x-ui.textarea :name="$key" :label="$label" :hint="$hint" :value="$value" class="md:col-span-2" wrapper-class="md:col-span-2" />
                            @break
                        @case('translatable_string')
                        @case('translatable_text')
                            <fieldset class="space-y-3 md:col-span-2">
                                <legend class="form-label">{{ $label }}</legend>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach (config('shop.locales') as $code => $locale)
                                        @if ($field['type'] === 'translatable_text')
                                            <x-ui.textarea :name="$key.'['.$code.']'" :label="$locale['native']" :value="$value[$code] ?? ''" rows="4" />
                                        @else
                                            <x-ui.input :name="$key.'['.$code.']'" :label="$locale['native']" :value="$value[$code] ?? ''" />
                                        @endif
                                    @endforeach
                                </div>
                                @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
                            </fieldset>
                            @break
                        @default
                            <x-ui.input :name="$key" :type="in_array($field['type'], ['email', 'url']) ? $field['type'] : 'text'" :label="$label" :hint="$hint" :value="$value" />
                    @endswitch
                @endforeach

                <div class="flex justify-end border-t border-line pt-4 md:col-span-2">
                    @can('settings.update')
                        <x-ui.button>{{ __('shop.save') }}</x-ui.button>
                    @endcan
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.admin>
