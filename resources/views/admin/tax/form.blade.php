@php($editing = $taxClass->exists)
@php($rows = old('rates', $rates))
@php($last = is_array($rows) ? ($rows[array_key_last($rows)] ?? []) : [])
@if (filled($last['percent'] ?? null) || filled($last['id'] ?? null))
    @php($rows[] = ['percent' => '', 'effective_from' => '', 'effective_to' => ''])
@endif

<x-layouts.admin :title="$editing ? __('admin.tax.edit') : __('admin.tax.create')">
    <x-ui.page-header :title="$editing ? __('admin.tax.edit') : __('admin.tax.create')" :back="route('admin.tax.index')" />

    <form method="POST" action="{{ $editing ? route('admin.tax.update', $taxClass) : route('admin.tax.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.basic_information')">
                <div class="grid gap-4 md:grid-cols-2">
                    @if ($editing)
                        <x-ui.input name="code_display" :label="__('admin.tax.code')" :value="$taxClass->code" disabled />
                    @else
                        <x-ui.input name="code" :label="__('admin.tax.code')" :value="$taxClass->code" :hint="__('admin.tax.code_hint')" required maxlength="40" />
                    @endif
                    <x-admin.translatable name="name" :label="__('admin.name_ja')" :value="$taxClass->name" required class="md:col-span-2" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.tax.rates')" :description="__('admin.tax.rates_hint')">
                @error('rates')<p class="form-error mb-4">{{ $message }}</p>@enderror
                <div class="space-y-4">
                    @foreach ($rows as $index => $row)
                        <div class="grid gap-3 rounded-xl border border-line p-3 md:grid-cols-3">
                            @if (! empty($row['id']))
                                <input type="hidden" name="rates[{{ $index }}][id]" value="{{ $row['id'] }}">
                            @endif
                            <x-ui.input name="rates[{{ $index }}][percent]" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('admin.tax.percent')" :value="$row['percent'] ?? ''" />
                            <x-ui.input name="rates[{{ $index }}][effective_from]" type="date" :label="__('admin.tax.effective_from')" :value="$row['effective_from'] ?? ''" />
                            <x-ui.input name="rates[{{ $index }}][effective_to]" type="date" :label="__('admin.tax.effective_to')" :value="$row['effective_to'] ?? ''" />
                            @if (! empty($row['id']))
                                <x-ui.checkbox name="rates[{{ $index }}][remove]" :label="__('admin.tax.remove')" class="md:col-span-3" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('admin.tax.is_default')">
                <x-ui.checkbox name="is_default" :label="__('admin.tax.default')" :checked="$taxClass->is_default" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('admin.tax.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
