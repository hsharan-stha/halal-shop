<x-layouts.shop :title="__('shop.halal_shops.title')">
    <div class="mx-auto max-w-5xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('shop.halal_shops.title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('shop.halal_shops.description') }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <form method="GET" class="flex min-w-0 flex-1 gap-2">
                <label for="shop-q" class="sr-only">{{ __('shop.halal_shops.search') }}</label>
                <input id="shop-q" type="search" name="q" value="{{ $term }}" placeholder="{{ __('shop.halal_shops.search') }}" class="form-control max-w-sm">
                <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
            </form>
            <button type="button" class="btn btn-primary" id="use-my-location">{{ __('shop.halal_shops.use_location') }}</button>
            <form id="location-form" method="POST" action="{{ route('halal-shops.location') }}" class="hidden">
                @csrf
                <input type="hidden" name="latitude" id="customer-latitude">
                <input type="hidden" name="longitude" id="customer-longitude">
            </form>
        </div>

    </div>

    <div class="mt-6">
        <x-shop.location-map :latitude="$latitude" :longitude="$longitude" :markers="$shops->map(fn ($shop) => ['lat' => $shop->latitude, 'lng' => $shop->longitude, 'label' => $shop->name, 'url' => route('halal-shops.show', $shop)])->all()" />
    </div>

    <div class="mx-auto max-w-5xl px-4 py-6 lg:px-6">
        <ul class="grid gap-3">
            @forelse ($shops as $shop)
                @php($distance = $shop->distanceKm(is_numeric($latitude) ? (float) $latitude : null, is_numeric($longitude) ? (float) $longitude : null))
                <li>
                    <a href="{{ route('halal-shops.show', $shop) }}" class="card flex items-start justify-between gap-3 p-4">
                        <span>
                            <span class="font-medium">{{ $shop->name }}</span>
                            <span class="mt-1 block text-sm text-ink-muted">{{ $shop->summary() }}</span>
                        </span>
                        @if ($distance !== null)
                            <span class="shrink-0 text-sm text-ink-muted">{{ number_format($distance, 1) }} km</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="text-sm text-ink-muted">{{ __('shop.halal_shops.empty') }}</li>
            @endforelse
        </ul>
    </div>
    <script>
        document.getElementById('use-my-location')?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                return;
            }
            navigator.geolocation.getCurrentPosition((position) => {
                document.getElementById('customer-latitude').value = position.coords.latitude;
                document.getElementById('customer-longitude').value = position.coords.longitude;
                document.getElementById('location-form').requestSubmit();
            });
        });
    </script>
</x-layouts.shop>
