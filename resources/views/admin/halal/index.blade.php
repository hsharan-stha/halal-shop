<x-layouts.admin :title="__('admin.nav.halal')">
    <x-ui.page-header :title="__('admin.halal.title')" :description="__('admin.halal.description')">
        <x-slot:actions>
            @can('halal_certificates.manage')
                <x-ui.button :href="route('admin.halal-certifications.create')" icon="plus">{{ __('admin.halal.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ([
            ['key' => 'verified', 'tone' => 'success', 'href' => route('admin.halal-certifications.index', ['status' => 'verified'])],
            ['key' => 'pending', 'tone' => 'warning', 'href' => route('admin.halal-certifications.index', ['status' => 'pending'])],
            ['key' => 'expiring', 'tone' => 'warning', 'href' => route('admin.halal-certifications.index', ['expiry' => 'expiring'])],
            ['key' => 'expired', 'tone' => 'danger', 'href' => route('admin.halal-certifications.index', ['expiry' => 'expired'])],
            ['key' => 'unsupported_products', 'tone' => 'danger', 'href' => Route::has('admin.products.index') ? route('admin.products.index', ['halal' => 'certified']) : null],
        ] as $card)
            <a href="{{ $card['href'] }}" class="card block p-4 transition hover:border-primary">
                <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.halal.summary.'.$card['key'], ['days' => $warningDays]) }}</p>
                <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-'.$card['tone'] => $summary[$card['key']] > 0 && $card['tone'] !== 'success'])>{{ number_format($summary[$card['key']]) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <div class="w-full sm:w-auto sm:flex-1 sm:max-w-sm">
            <label for="halal-q" class="sr-only">{{ __('admin.search') }}</label>
            <input id="halal-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.halal.search_placeholder') }}" class="form-control">
        </div>
        <label for="halal-status" class="sr-only">{{ __('admin.status') }}</label>
        <select id="halal-status" name="status" class="form-control w-auto">
            <option value="">{{ __('admin.halal.all_statuses') }}</option>
            @foreach (\App\Enums\CertificationStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($filters['expiry'] ?? null)<input type="hidden" name="expiry" value="{{ $filters['expiry'] }}">@endif
        <x-ui.button variant="secondary">{{ __('admin.search') }}</x-ui.button>
        @if (array_filter($filters))
            <x-ui.button variant="ghost" :href="route('admin.halal-certifications.index')">{{ __('admin.reset') }}</x-ui.button>
        @endif
    </form>

    @if ($certifications->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="shield-check" :title="__('admin.halal.empty')" :description="__('admin.halal.empty_hint')">
                @can('halal_certificates.manage')
                    <x-ui.button :href="route('admin.halal-certifications.create')" icon="plus">{{ __('admin.halal.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.halal.certifying_body') }}</th>
                <th>{{ __('admin.brands.title') }}</th>
                <th>{{ __('admin.halal.expires_at') }}</th>
                <th class="text-right">{{ __('admin.products.title') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($certifications as $certification)
                <tr>
                    <td>
                        <p class="font-medium">{{ $certification->certifying_body }}</p>
                        <p class="font-mono text-xs text-ink-muted">#{{ $certification->certificate_number }}</p>
                    </td>
                    <td class="text-sm">{{ $certification->brand?->localizedName() ?? '—' }}</td>
                    <td class="text-sm whitespace-nowrap">@include('admin.halal._expiry')</td>
                    <td class="text-right tabular-nums">{{ number_format($certification->products_count) }}</td>
                    <td><x-ui.badge :color="$certification->status->color()">{{ $certification->status->label() }}</x-ui.badge></td>
                    <td class="text-right"><x-ui.button variant="ghost" size="sm" :href="route('admin.halal-certifications.show', $certification)" icon="eye">{{ __('admin.view') }}</x-ui.button></td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($certifications as $certification)
                    <a href="{{ route('admin.halal-certifications.show', $certification) }}" class="block space-y-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $certification->certifying_body }}</p>
                                <p class="font-mono text-xs text-ink-muted">#{{ $certification->certificate_number }}</p>
                            </div>
                            <x-ui.badge :color="$certification->status->color()">{{ $certification->status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-xs">@include('admin.halal._expiry') · {{ trans_choice('admin.categories.product_count', $certification->products_count, ['count' => $certification->products_count]) }}</p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $certifications->links() }}</div>
    @endif
</x-layouts.admin>
