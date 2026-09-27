@props(['latitude' => null, 'longitude' => null, 'editable' => false, 'markers' => []])

@php
    $points = collect($markers)->map(fn ($marker) => [
        'lat' => (float) $marker['lat'],
        'lng' => (float) $marker['lng'],
        'label' => $marker['label'],
        'url' => $marker['url'] ?? null,
    ])->values();
    $startLat = $latitude ?? ($points->first()['lat'] ?? 36.2);
    $startLng = $longitude ?? ($points->first()['lng'] ?? 138.2);
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<div id="shop-map" class="z-0 w-full overflow-hidden" style="height: 100dvh"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const markers = @json($points);
        const latInput = document.getElementById('shop-latitude');
        const lngInput = document.getElementById('shop-longitude');
        const hasSavedPin = latInput && lngInput && latInput.value !== '' && lngInput.value !== '';
        const start = hasSavedPin
            ? [Number(latInput.value), Number(lngInput.value)]
            : [{{ $startLat }}, {{ $startLng }}];
        const map = L.map('shop-map').setView(start, {{ $editable ? '(hasSavedPin ? 15 : 5)' : ($points->count() > 1 ? 6 : 13) }});
        requestAnimationFrame(() => map.invalidateSize());

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        markers.forEach((point) => {
            const marker = L.marker([point.lat, point.lng]).addTo(map);
            if (point.label) {
                const label = document.createElement('span');
                label.textContent = point.label;
                if (point.url) {
                    const link = document.createElement('a');
                    link.href = point.url;
                    link.append(label);
                    marker.bindPopup(link);
                } else {
                    marker.bindPopup(label);
                }
            }
        });

        @if ($editable)
            const status = document.getElementById('shop-map-status');
            const form = latInput?.closest('form');
            let pin = null;

            const setStatus = (key) => {
                if (! status) {
                    return;
                }
                status.textContent = status.dataset[key] || '';
                status.classList.toggle('text-danger', key === 'missing' || key === 'notFound' || key === 'needAddress');
            };

            const place = (latitude, longitude) => {
                if (pin) {
                    pin.setLatLng([latitude, longitude]);
                } else {
                    pin = L.marker([latitude, longitude]).addTo(map);
                }
                latInput.value = Number(latitude).toFixed(7);
                lngInput.value = Number(longitude).toFixed(7);
                setStatus('set');
            };

            if (hasSavedPin) {
                place(latInput.value, lngInput.value);
            } else {
                setStatus('empty');
            }

            map.on('click', (event) => place(event.latlng.lat, event.latlng.lng));

            const findPlace = async (query) => {
                const term = query.trim();

                if (term === '') {
                    setStatus('needPlace');
                    return;
                }

                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=jp&q=' + encodeURIComponent(term);
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const results = response.ok ? await response.json() : [];

                    if (! results[0]) {
                        setStatus('notFound');
                        return;
                    }

                    const latitude = Number(results[0].lat);
                    const longitude = Number(results[0].lon);
                    map.setView([latitude, longitude], 16);
                    place(latitude, longitude);
                    document.getElementById('shop-map')?.scrollIntoView({ block: 'center' });
                } catch {
                    setStatus('notFound');
                }
            };

            document.getElementById('shop-place-search')?.addEventListener('click', () => {
                findPlace(document.getElementById('shop-place-query')?.value ?? '');
            });

            document.getElementById('shop-place-query')?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                findPlace(event.currentTarget.value);
            });

            document.getElementById('shop-find-address')?.addEventListener('click', () => {
                const parts = ['postal_code', 'prefecture', 'city', 'town', 'street']
                    .map((name) => form?.querySelector(`[name="${name}"]`)?.value?.trim())
                    .filter(Boolean);

                if (parts.length < 2) {
                    setStatus('needAddress');
                    return;
                }

                findPlace(parts.join(' '));
            });

            form?.addEventListener('submit', (event) => {
                if (latInput.value === '' || lngInput.value === '') {
                    event.preventDefault();
                    setStatus('missing');
                    document.getElementById('shop-map')?.scrollIntoView({ block: 'center' });
                }
            });
        @endif
    });
</script>
