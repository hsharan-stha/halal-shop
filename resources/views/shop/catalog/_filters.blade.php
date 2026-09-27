{{-- GET catalog filters. $action, $filters, $options, $locked (list of fields owned by the page). --}}
<form id="catalog-filters" method="GET" action="{{ $action }}" class="space-y-4">
    <div>
        <label for="catalog-q" class="form-label">{{ __('shop.search.placeholder') }}</label>
        <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" class="form-control" placeholder="{{ __('shop.search.placeholder') }}" enterkeyhint="search">
    </div>

    @unless (in_array('category', $locked, true))
        <x-ui.select name="category" :label="__('shop.catalog.category')" :options="$options['categories']" :value="$filters['category']" :placeholder="__('shop.catalog.any')" />
    @endunless

    @unless (in_array('brand', $locked, true))
        <x-ui.select name="brand" :label="__('shop.catalog.brand')" :options="$options['brands']" :value="$filters['brand']" :placeholder="__('shop.catalog.any')" />
    @endunless

    <x-ui.select name="halal" :label="__('shop.catalog.halal')" :options="\App\Enums\HalalStatus::options()" :value="$filters['halal']" :placeholder="__('shop.catalog.any')" />
    <x-ui.select name="storage" :label="__('shop.catalog.storage')" :options="\App\Enums\StorageType::options()" :value="$filters['storage']" :placeholder="__('shop.catalog.any')" />
    <x-ui.select name="availability" :label="__('shop.catalog.availability')" :options="['in_stock' => __('shop.catalog.in_stock'), 'out_of_stock' => __('shop.catalog.out_of_stock')]" :value="$filters['availability']" :placeholder="__('shop.catalog.any')" />

    @if ($options['countries'] !== [])
        <x-ui.select name="country" :label="__('shop.catalog.country')" :options="$options['countries']" :value="$filters['country']" :placeholder="__('shop.catalog.any')" />
    @endif

    <div class="grid grid-cols-2 gap-2">
        <x-ui.input name="min_price" type="number" min="0" step="1" inputmode="numeric" :label="__('shop.catalog.min_price')" :value="$filters['min_price']" />
        <x-ui.input name="max_price" type="number" min="0" step="1" inputmode="numeric" :label="__('shop.catalog.max_price')" :value="$filters['max_price']" />
    </div>

    <div class="flex gap-2">
        <x-ui.button icon="filter" class="flex-1">{{ __('shop.catalog.apply') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="$action">{{ __('shop.catalog.clear') }}</x-ui.button>
    </div>
</form>
