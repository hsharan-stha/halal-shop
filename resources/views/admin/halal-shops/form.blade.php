@php($editing = $shop->exists)

<x-layouts.admin :title="$editing ? $shop->name : __('admin.halal_shops.create')">
    <x-ui.page-header :title="$editing ? $shop->name : __('admin.halal_shops.create')" :back="$owns ? route('admin.shop-sales.index') : route('admin.halal-shops.index')" />

    <form method="POST" action="{{ $owns ? route('admin.my-shop.update') : ($editing ? route('admin.halal-shops.update', $shop) : route('admin.halal-shops.store')) }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card class="space-y-4">
            @unless ($owns)
                <x-ui.input name="name" :label="__('admin.halal_shops.name')" :value="$shop->name" required />
            @endunless
            <x-ui.input name="phone" type="tel" :label="__('shop.fields.phone')" :value="$shop->phone" />
            <x-ui.input name="email" type="email" :label="__('shop.fields.email')" :value="$shop->email" />
            <x-ui.input name="postal_code" :label="__('shop.checkout.postal_code')" :value="$shop->postal_code" required placeholder="123-4567" />
            <x-ui.select name="prefecture" :label="__('shop.checkout.prefecture')" :options="\App\Support\Prefectures::options()" :value="$shop->prefecture" required :placeholder="__('shop.checkout.prefecture')" />
            <x-ui.input name="city" :label="__('shop.checkout.city')" :value="$shop->city" required />
            <x-ui.input name="town" :label="__('shop.checkout.town')" :value="$shop->town" required />
            <x-ui.input name="street" :label="__('shop.checkout.street')" :value="$shop->street" required />
            <x-ui.input name="building" :label="__('shop.checkout.building')" :value="$shop->building" />
            @unless ($owns)
                <x-ui.checkbox name="is_active" :label="__('admin.halal_shops.active')" :checked="$shop->is_active" />
            @endunless
        </x-ui.card>

            @unless ($owns)
                <x-ui.card class="space-y-4">
                    <h2 class="text-sm font-semibold">{{ __('admin.halal_shops.login') }}</h2>
                    <p class="text-sm text-ink-muted">{{ __('admin.halal_shops.login_hint') }}</p>
                    <x-ui.input name="user_name" :label="__('admin.halal_shops.login_name')" :required="! $editing" />
                    <x-ui.input name="user_email" type="email" :label="__('admin.halal_shops.login_email')" :required="! $editing" autocomplete="off" />
                    <x-ui.input name="user_password" type="password" :label="__('shop.fields.password')" :required="! $editing" autocomplete="new-password" />
                    <x-ui.input name="user_password_confirmation" type="password" :label="__('shop.fields.password_confirmation')" :required="! $editing" autocomplete="new-password" />
                </x-ui.card>
            @endunless
        </div>

        <div class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold">{{ __('admin.halal_shops.map') }} <span class="text-danger" aria-hidden="true">*</span></h2>
                <p class="mt-1 text-sm text-ink-muted">{{ __('admin.halal_shops.map_hint') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="shop-place-query" class="form-label">{{ __('admin.halal_shops.place_search') }}</label>
                    <input id="shop-place-query" type="search" class="form-control" placeholder="{{ __('admin.halal_shops.place_search_placeholder') }}" autocomplete="off">
                </div>
                <x-ui.button type="button" id="shop-place-search" variant="secondary">{{ __('admin.halal_shops.search_place') }}</x-ui.button>
            </div>
            <x-ui.button type="button" id="shop-find-address" variant="secondary">{{ __('admin.halal_shops.find_on_map') }}</x-ui.button>
            <p id="shop-map-status" class="text-sm text-ink-muted"
               data-empty="{{ __('admin.halal_shops.map_empty') }}"
               data-set="{{ __('admin.halal_shops.map_set') }}"
               data-missing="{{ __('admin.halal_shops.map_missing') }}"
               data-not-found="{{ __('admin.halal_shops.map_not_found') }}"
               data-need-address="{{ __('admin.halal_shops.map_need_address') }}"
               data-need-place="{{ __('admin.halal_shops.map_need_place') }}">{{ $shop->latitude ? __('admin.halal_shops.map_set') : __('admin.halal_shops.map_empty') }}</p>
            <div class="-mx-3 sm:-mx-6">
                <x-shop.location-map :latitude="$shop->latitude" :longitude="$shop->longitude" editable />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input id="shop-latitude" name="latitude" :label="__('admin.halal_shops.latitude')" :value="$shop->latitude" readonly />
                <x-ui.input id="shop-longitude" name="longitude" :label="__('admin.halal_shops.longitude')" :value="$shop->longitude" readonly />
            </div>
        </div>

        <div>
            <x-ui.button>{{ __('shop.save') }}</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
