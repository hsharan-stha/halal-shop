@php($editing = $brand->exists)

<x-layouts.admin :title="$editing ? __('admin.brands.edit') : __('admin.brands.create')">
    <x-ui.page-header :title="$editing ? __('admin.brands.edit') : __('admin.brands.create')" :back="route('admin.brands.index')" />

    <form method="POST" action="{{ $editing ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.basic_information')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('admin.name_en')" :value="$brand->name" required maxlength="120" />
                    <x-ui.input name="japanese_name" :label="__('admin.name_ja')" :value="$brand->japanese_name" maxlength="120" lang="ja" />
                    <x-ui.input name="slug" :label="__('admin.slug')" :value="$brand->slug" :hint="__('admin.slug_hint')" maxlength="140" />
                    <x-ui.input name="website_url" type="url" :label="__('admin.brands.website')" :value="$brand->website_url" placeholder="https://" />
                    <x-ui.input name="country_of_origin" :label="__('admin.country_code')" :value="$brand->country_of_origin" :hint="__('admin.country_code_hint')" maxlength="2" class="uppercase" />
                    <x-admin.translatable name="description" :label="__('admin.description')" :value="$brand->description" textarea class="md:col-span-2" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('admin.organization')">
                <div class="space-y-4">
                    <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$brand->sort_order ?? 0" />
                    <x-ui.checkbox name="is_active" :label="__('admin.brands.visible')" :checked="$brand->is_active" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.brands.logo')">
                <x-ui.file-upload name="logo" :current="$brand->logoUrl()" :removable="(bool) $brand->logo_path" :hint="__('admin.image_hint')" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('admin.brands.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
