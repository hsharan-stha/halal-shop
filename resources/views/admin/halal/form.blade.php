@php($editing = $certification->exists)

<x-layouts.admin :title="$editing ? __('admin.halal.edit') : __('admin.halal.create')">
    <x-ui.page-header :title="$editing ? __('admin.halal.edit') : __('admin.halal.create')" :back="$editing ? route('admin.halal-certifications.show', $certification) : route('admin.halal-certifications.index')" />

    @if ($editing && $certification->status === \App\Enums\CertificationStatus::Verified)
        <x-ui.alert type="warning" class="mb-4">{{ __('admin.halal.edit_resets_verification') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.halal-certifications.update', $certification) : route('admin.halal-certifications.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.halal.certificate_details')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="certifying_body" :label="__('admin.halal.certifying_body')" :value="$certification->certifying_body" required maxlength="150" :hint="__('admin.halal.certifying_body_hint')" />
                    <x-ui.input name="certificate_number" :label="__('admin.halal.certificate_number')" :value="$certification->certificate_number" required maxlength="100" class="font-mono" />
                    <x-ui.input name="issued_at" type="date" :label="__('admin.halal.issued_at')" :value="$certification->issued_at?->toDateString()" />
                    <x-ui.input name="expires_at" type="date" :label="__('admin.halal.expires_at')" :value="$certification->expires_at?->toDateString()" required />
                    <x-ui.select name="brand_id" :label="__('admin.halal.brand')" :options="$brands" :value="$certification->brand_id" :placeholder="__('admin.none')" :hint="__('admin.halal.brand_hint')" />
                    <x-ui.input name="scope" :label="__('admin.halal.scope')" :value="$certification->scope" maxlength="255" :hint="__('admin.halal.scope_hint')" />
                    <x-ui.textarea name="notes" :label="__('admin.halal.internal_notes')" :value="$certification->notes" :rows="3" maxlength="2000" wrapper-class="md:col-span-2" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('admin.halal.covered_products')" :description="__('admin.halal.covered_products_hint')">
                <div x-data="{ filter: '' }" class="space-y-3">
                    <label for="product-filter" class="sr-only">{{ __('admin.search') }}</label>
                    <input id="product-filter" type="search" x-model="filter" placeholder="{{ __('admin.products.search_placeholder') }}" class="form-control">
                    @if ($products->isEmpty())
                        <p class="text-sm text-ink-muted">{{ __('admin.products.empty') }}</p>
                    @else
                        <div class="max-h-80 divide-y divide-line overflow-y-auto rounded-xl border border-line">
                            @foreach ($products as $product)
                                @php($haystack = mb_strtolower($product->name.' '.$product->japanese_name.' '.$product->sku))
                                <label class="flex cursor-pointer items-center gap-3 p-3 hover:bg-surface-muted/50" x-show="filter === '' || @js($haystack).includes(filter.toLowerCase())">
                                    <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="form-check"
                                           @checked(in_array($product->id, array_map('intval', (array) old('product_ids', $selectedProductIds)), true))>
                                    <span class="min-w-0 text-sm">
                                        <span class="font-medium">{{ $product->localizedName() }}</span>
                                        <span class="font-mono text-xs text-ink-muted">{{ $product->sku }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('product_ids')<p class="form-error">{{ $message }}</p>@enderror
                    @error('product_ids.*')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.halal.certificate_file')">
                <x-ui.file-upload name="file" accept="application/pdf,image/jpeg,image/png,image/webp" :hint="__('admin.halal.file_hint')" :required="! $editing" />
                @if ($editing && $certification->hasFile())
                    <p class="form-hint mt-2">{{ __('admin.halal.current_file', ['name' => $certification->file_name]) }}</p>
                @endif
            </x-ui.card>

            <x-ui.alert type="info">{{ __('admin.halal.verification_notice') }}</x-ui.alert>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="$editing ? route('admin.halal-certifications.show', $certification) : route('admin.halal-certifications.index')">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
