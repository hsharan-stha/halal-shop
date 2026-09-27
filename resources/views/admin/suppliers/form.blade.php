@php
    $title = $supplier->exists ? __('admin.suppliers.edit') : __('admin.suppliers.create');
    $country = strtoupper((string) old('country_code', $supplier->country_code ?: 'JP'));
@endphp

<x-layouts.admin :title="$title">
    <x-ui.page-header :title="$title" :back="$supplier->exists ? route('admin.suppliers.show', $supplier) : route('admin.suppliers.index')" />

    <form method="POST" action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier) : route('admin.suppliers.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($supplier->exists)
            @method('PUT')
        @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.suppliers.basic')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('admin.suppliers.name')" :value="$supplier->name" required maxlength="120" />
                    <x-ui.input name="company_name" :label="__('admin.suppliers.company_name')" :value="$supplier->company_name" maxlength="160" :hint="__('admin.suppliers.company_name_hint')" />
                    <x-ui.input name="code" :label="__('admin.suppliers.code')" :value="$supplier->code" maxlength="40" class="uppercase" :hint="__('admin.suppliers.code_hint')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.suppliers.contact')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="contact_name" :label="__('admin.suppliers.contact_name')" :value="$supplier->contact_name" maxlength="120" autocomplete="off" />
                    <x-ui.input name="email" type="email" :label="__('admin.suppliers.email')" :value="$supplier->email" maxlength="255" autocomplete="off" />
                    <x-ui.input name="phone" type="tel" :label="__('admin.suppliers.phone')" :value="$supplier->phone" maxlength="30" :hint="__('admin.suppliers.phone_hint')" autocomplete="off" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.suppliers.address_section')">
                <div class="grid gap-4 md:grid-cols-2" x-data="{ country: @js($country) }">
                    <x-ui.input name="country_code" :label="__('admin.suppliers.country')" :value="$country" required maxlength="2" class="uppercase"
                                :hint="__('admin.country_code_hint')" x-model="country" />
                    <x-ui.input name="postal_code" :label="__('admin.suppliers.postal_code')" :value="$supplier->postal_code" maxlength="10" inputmode="numeric"
                                :placeholder="$country === 'JP' ? '123-4567' : null" />
                    <div x-show="country.toUpperCase() === 'JP'">
                        <x-ui.select name="prefecture" id="f-prefecture-jp" :label="__('admin.suppliers.prefecture')" :options="\App\Support\Prefectures::options()"
                                     :value="$country === 'JP' ? $supplier->prefecture : null" :placeholder="__('admin.none')" x-bind:disabled="country.toUpperCase() !== 'JP'" />
                    </div>
                    <div x-show="country.toUpperCase() !== 'JP'" x-cloak>
                        <x-ui.input name="prefecture" id="f-prefecture-other" :label="__('admin.suppliers.region')" :value="$country !== 'JP' ? $supplier->prefecture : null" maxlength="20"
                                    x-bind:disabled="country.toUpperCase() === 'JP'" />
                    </div>
                    <x-ui.input name="address" :label="__('admin.suppliers.address')" :value="$supplier->address" maxlength="255" wrapperClass="md:col-span-2" />
                </div>
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.status')">
                <x-ui.checkbox name="is_active" :label="__('admin.suppliers.is_active')" :checked="$supplier->is_active" :hint="__('admin.suppliers.is_active_hint')" />
            </x-ui.card>

            <x-ui.card :title="__('admin.suppliers.notes')">
                <x-ui.textarea name="notes" :value="$supplier->notes" :rows="5" maxlength="5000" :hint="__('admin.suppliers.notes_hint')" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="$supplier->exists ? route('admin.suppliers.show', $supplier) : route('admin.suppliers.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
