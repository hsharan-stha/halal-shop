@php($editing = $category->exists)

<x-layouts.admin :title="$editing ? __('admin.categories.edit') : __('admin.categories.create')">
    <x-ui.page-header :title="$editing ? __('admin.categories.edit') : __('admin.categories.create')" :back="route('admin.categories.index')" />

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.basic_information')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('admin.name_en')" :value="$category->name" required maxlength="120" />
                    <x-ui.input name="japanese_name" :label="__('admin.name_ja')" :value="$category->japanese_name" maxlength="120" lang="ja" />
                    <x-ui.input name="slug" :label="__('admin.slug')" :value="$category->slug" :hint="__('admin.slug_hint')" maxlength="140" wrapper-class="md:col-span-2" />
                    <x-admin.translatable name="description" :label="__('admin.description')" :value="$category->description" textarea class="md:col-span-2" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.seo')">
                <div class="space-y-4">
                    <x-admin.translatable name="meta_title" :label="__('admin.meta_title')" :value="$category->meta_title" />
                    <x-admin.translatable name="meta_description" :label="__('admin.meta_description')" :value="$category->meta_description" textarea :rows="2" />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card :title="__('admin.organization')">
                <div class="space-y-4">
                    <x-ui.select name="parent_id" :label="__('admin.categories.parent')" :options="$parents" :value="$category->parent_id" :placeholder="__('admin.categories.no_parent')" />
                    <x-ui.select name="icon" :label="__('admin.categories.icon')" :options="array_combine(\App\Models\Category::ICONS, \App\Models\Category::ICONS)" :value="$category->icon" :placeholder="__('admin.none')" />
                    <x-ui.input name="sort_order" type="number" min="0" :label="__('admin.sort_order')" :value="$category->sort_order ?? 0" />
                    <x-ui.checkbox name="is_active" :label="__('admin.categories.visible')" :checked="$category->is_active" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.image')">
                <x-ui.file-upload name="image" :current="$category->imageUrl()" :removable="(bool) $category->image_path" :hint="__('admin.image_hint')" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('admin.categories.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
