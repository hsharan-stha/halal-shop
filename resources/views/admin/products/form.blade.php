@php
    $editing = $product->exists;
    $selectedAllergens = collect($product->allergens ?? [])->map(fn ($allergen) => $allergen->value)->all();
    $selectedCertifications = $editing ? $product->halalCertifications->pluck('id')->all() : [];
    $nutrition = $product->nutrition ?? [];
    $pricesIncludeTax = (bool) settings('tax.prices_include_tax');
@endphp

<x-layouts.admin :title="$editing ? $product->localizedName() : __('admin.products.create')">
    <x-ui.page-header :title="$editing ? $product->localizedName() : __('admin.products.create')" :back="route('admin.products.index')">
        @if ($editing)
            <x-slot:actions>
                <x-ui.badge :color="$product->status->color()">{{ $product->status->label() }}</x-ui.badge>
                @can('products.create')
                    <form method="POST" action="{{ route('admin.products.duplicate', $product) }}">
                        @csrf
                        <x-ui.button variant="secondary" size="sm" icon="copy">{{ __('admin.products.duplicate') }}</x-ui.button>
                    </form>
                @endcan
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert type="danger" class="mb-4">{{ __('admin.form_has_errors') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.basic_information')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="name" :label="__('admin.name_en')" :value="$product->name" required maxlength="200" />
                    <x-ui.input name="japanese_name" :label="__('admin.name_ja')" :value="$product->japanese_name" maxlength="200" lang="ja" />
                    <x-ui.input name="sku" :label="__('admin.products.sku')" :value="$product->sku" required maxlength="64" class="font-mono uppercase" :hint="__('admin.products.sku_hint')" />
                    <x-ui.input name="slug" :label="__('admin.slug')" :value="$product->slug" maxlength="180" :hint="__('admin.slug_hint')" />
                    <x-admin.translatable name="short_description" :label="__('admin.products.short_description')" :value="$product->short_description" textarea :rows="2" class="md:col-span-2" />
                    <x-admin.translatable name="description" :label="__('admin.description')" :value="$product->description" textarea :rows="8" class="md:col-span-2" />
                </div>
            </x-ui.card>

            @unless ($editing)
                <x-ui.card :title="__('admin.products.pricing')" :description="$pricesIncludeTax ? __('admin.products.prices_include_tax') : __('admin.products.prices_exclude_tax')">
                    <div class="grid gap-4 md:grid-cols-3">
                        <x-ui.input name="variant[price]" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.price')" required />
                        <x-ui.input name="variant[compare_at_price]" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.compare_at_price')" :hint="__('admin.variants.compare_at_hint')" />
                        <x-ui.input name="variant[cost_price]" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.cost_price')" :hint="__('admin.variants.cost_hint')" />
                        <x-ui.input name="variant[sku]" :label="__('admin.variants.sku')" class="font-mono uppercase" :hint="__('admin.variants.sku_default_hint')" maxlength="64" />
                        <x-ui.input name="variant[barcode]" inputmode="numeric" :label="__('admin.variants.barcode')" maxlength="14" />
                        <x-ui.input name="variant[weight_grams]" type="number" min="0" step="1" inputmode="numeric" :label="__('admin.variants.weight_grams')" :hint="__('admin.variants.weight_hint')" />
                    </div>
                </x-ui.card>
            @endunless

            <x-ui.card :title="__('admin.halal.section')" :description="__('admin.halal.section_hint')">
                <div class="space-y-5">
                    <fieldset>
                        <legend class="form-label">{{ __('admin.halal.status') }} <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="grid gap-2 md:grid-cols-2">
                            @foreach (\App\Enums\HalalStatus::cases() as $status)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 has-checked:border-primary has-checked:bg-primary-soft/40">
                                    <input type="radio" name="halal_status" value="{{ $status->value }}" class="mt-1 size-4 accent-(--brand-primary)" @checked(old('halal_status', $product->halal_status?->value) === $status->value) required>
                                    <span class="text-sm">
                                        <span class="font-medium text-ink">{{ $status->label() }}</span>
                                        <span class="block text-xs text-ink-muted">{{ __('admin.halal.status_hints.'.$status->value) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('halal_status')<p class="form-error">{{ $message }}</p>@enderror
                    </fieldset>

                    @if ($editing && $product->halal_status->requiresCertificate() && ! $product->isCertifiedHalal())
                        <x-ui.alert type="warning">{{ __('admin.halal.certified_without_valid_certificate') }}</x-ui.alert>
                    @endif

                    <fieldset>
                        <legend class="form-label">{{ __('admin.halal.linked_certificates') }}</legend>
                        <p class="form-hint mb-2">{{ __('admin.halal.linked_certificates_hint') }}</p>
                        @if ($certifications->isEmpty())
                            <p class="rounded-xl border border-dashed border-line p-4 text-sm text-ink-muted">{{ __('admin.halal.no_certificates') }}</p>
                        @else
                            <div class="max-h-72 divide-y divide-line overflow-y-auto rounded-xl border border-line">
                                @foreach ($certifications as $certification)
                                    <label class="flex cursor-pointer items-start gap-3 p-3 hover:bg-surface-muted/50">
                                        <input type="checkbox" name="halal_certification_ids[]" value="{{ $certification->id }}" class="form-check mt-0.5"
                                               @checked(in_array($certification->id, array_map('intval', (array) old('halal_certification_ids', $selectedCertifications)), true))>
                                        <span class="min-w-0 flex-1 text-sm">
                                            <span class="font-medium">{{ $certification->certifying_body }}</span>
                                            <span class="font-mono text-xs text-ink-muted">#{{ $certification->certificate_number }}</span>
                                            <span class="mt-1 flex flex-wrap items-center gap-1 text-xs text-ink-muted">
                                                <x-ui.badge :color="$certification->status->color()">{{ $certification->status->label() }}</x-ui.badge>
                                                @if ($certification->isExpired())<x-ui.badge color="danger">{{ __('admin.halal.expired') }}</x-ui.badge>@endif
                                                {{ __('admin.halal.expires_on', ['date' => local_date($certification->expires_at)]) }}
                                                @if ($certification->brand) · {{ $certification->brand->name }}@endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('halal_certification_ids')<p class="form-error">{{ $message }}</p>@enderror
                        @can('halal_certificates.manage')
                            <a href="{{ route('admin.halal-certifications.create', $editing ? ['product' => $product->id] : []) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="plus" class="size-4" />{{ __('admin.halal.create') }}</a>
                        @endcan
                    </fieldset>

                    <x-admin.translatable name="halal_notes" :label="__('admin.halal.notes')" :value="$product->halal_notes" textarea :rows="2" :hint="__('admin.halal.notes_hint')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.food_label.title')" :description="__('admin.food_label.description')">
                <div class="space-y-5">
                    <x-admin.translatable name="ingredients" :label="__('admin.food_label.ingredients')" :value="$product->ingredients" textarea :rows="3" :hint="__('admin.food_label.ingredients_hint')" />

                    <fieldset>
                        <legend class="form-label">{{ __('admin.food_label.allergens') }}</legend>
                        <p class="form-hint mb-2">{{ __('admin.food_label.allergens_hint') }}</p>
                        @foreach ([true, false] as $mandatory)
                            <p class="mt-3 mb-1 text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ $mandatory ? __('admin.food_label.allergens_mandatory') : __('admin.food_label.allergens_recommended') }}</p>
                            <div class="grid grid-cols-2 gap-x-4 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach (array_filter(\App\Enums\Allergen::cases(), fn ($allergen) => $allergen->isMandatory() === $mandatory) as $allergen)
                                    <x-ui.checkbox name="allergens[]" :value="$allergen->value" :label="$allergen->label()" :checked="in_array($allergen->value, $selectedAllergens, true)" />
                                @endforeach
                            </div>
                        @endforeach
                    </fieldset>

                    <fieldset>
                        <legend class="form-label">{{ __('admin.food_label.nutrition') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.input name="nutrition[basis]" :label="__('admin.food_label.nutrition_basis')" :value="$nutrition['basis'] ?? null" :placeholder="__('admin.food_label.nutrition_basis_placeholder')" maxlength="40" />
                            @foreach (['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'salt_g'] as $field)
                                <x-ui.input :name="'nutrition['.$field.']'" type="number" min="0" step="0.01" inputmode="decimal" :label="__('admin.food_label.nutrition_fields.'.$field)" :value="$nutrition[$field] ?? null" />
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.select name="storage_type" :label="__('admin.food_label.storage_type')" :options="\App\Enums\StorageType::options()" :value="$product->storage_type ?? \App\Enums\StorageType::Ambient" required />
                        <x-ui.input name="net_content" :label="__('admin.food_label.net_content')" :value="$product->net_content" :placeholder="__('admin.food_label.net_content_placeholder')" maxlength="60" />
                        <x-ui.input name="country_of_origin" :label="__('admin.food_label.country_of_origin')" :value="$product->country_of_origin" :hint="__('admin.country_code_hint')" maxlength="2" class="uppercase" />
                        <x-ui.input name="manufacturer" :label="__('admin.food_label.manufacturer')" :value="$product->manufacturer" maxlength="255" />
                        <x-ui.input name="importer" :label="__('admin.food_label.importer')" :value="$product->importer" maxlength="255" wrapper-class="md:col-span-2" />
                    </div>

                    <x-admin.translatable name="storage_instructions" :label="__('admin.food_label.storage_instructions')" :value="$product->storage_instructions" textarea :rows="2" />

                    <div class="rounded-xl border border-line bg-surface-muted/40 p-4">
                        @if ($product->hasReviewedFoodLabel())
                            <p class="mb-2 flex items-center gap-2 text-sm text-success"><x-icon name="check-circle" class="size-5" />{{ __('admin.food_label.reviewed_by', ['name' => $product->foodLabelReviewer?->name ?? '—', 'date' => local_date($product->food_label_reviewed_at, true)]) }}</p>
                        @else
                            <p class="mb-2 flex items-center gap-2 text-sm text-warning"><x-icon name="alert" class="size-5" />{{ __('admin.food_label.not_reviewed_notice') }}</p>
                        @endif
                        <x-ui.checkbox name="food_label_reviewed" :label="__('admin.food_label.confirm')" :hint="__('admin.food_label.confirm_hint')" />
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.seo')">
                <div class="space-y-4">
                    <x-admin.translatable name="meta_title" :label="__('admin.meta_title')" :value="$product->meta_title" />
                    <x-admin.translatable name="meta_description" :label="__('admin.meta_description')" :value="$product->meta_description" textarea :rows="2" />
                </div>
            </x-ui.card>

            @unless ($editing)
                <x-ui.card :title="__('admin.images.title')">
                    <x-ui.file-upload name="images[]" multiple :hint="__('admin.images.hint')" />
                </x-ui.card>
            @endunless
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.products.publishing')">
                <div class="space-y-4">
                    <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\ProductStatus::options()" :value="$product->status" required />
                    <x-ui.input name="published_at" type="datetime-local" :label="__('admin.products.published_at')" :value="local_time($product->published_at)?->format('Y-m-d\TH:i')" :hint="__('admin.products.published_at_hint')" />
                    <x-ui.checkbox name="is_featured" :label="__('admin.products.featured')" :checked="$product->is_featured" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.organization')">
                <div class="space-y-4">
                    <x-ui.select name="category_id" :label="__('admin.categories.title')" :options="$categories" :value="$product->category_id" :placeholder="__('admin.choose')" required />
                    <x-ui.select name="brand_id" :label="__('admin.brands.title')" :options="$brands" :value="$product->brand_id" :placeholder="__('admin.none')" />
                    <x-ui.select name="supplier_id" :label="__('admin.products.supplier')" :options="$suppliers" :value="$product->supplier_id" :placeholder="__('admin.none')" />
                    <x-ui.select name="tax_class_id" :label="__('admin.products.tax_class')" :options="$taxClasses" :value="$product->tax_class_id" required />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.products.order_limits')">
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="min_order_quantity" type="number" min="1" max="999" :label="__('admin.products.min_quantity')" :value="$product->min_order_quantity ?? 1" required />
                    <x-ui.input name="max_order_quantity" type="number" min="1" max="999" :label="__('admin.products.max_quantity')" :value="$product->max_order_quantity" />
                </div>
            </x-ui.card>

            <div class="sticky bottom-4 z-10 flex justify-end gap-2 rounded-xl border border-line bg-surface/95 p-3 shadow-sm backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
                <x-ui.button variant="secondary" :href="route('admin.products.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>

    @if ($editing)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-6 lg:col-span-2">
                <livewire:admin.product-variants :product="$product" />
                <livewire:admin.product-images :product="$product" />
            </div>
        </div>
    @endif
</x-layouts.admin>
