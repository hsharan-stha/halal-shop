<x-layouts.admin :title="__('admin.nav.batches')">
    <x-ui.page-header :title="__('admin.batches.title')" :description="__('admin.batches.description')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="archive" :href="route('admin.inventory.index')">{{ __('admin.nav.inventory') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['key' => 'available', 'tone' => null, 'href' => route('admin.batches.index', ['status' => 'available'])],
            ['key' => 'quarantined', 'tone' => 'warning', 'href' => route('admin.batches.index', ['status' => 'quarantined'])],
            ['key' => 'expiring', 'tone' => 'warning', 'href' => route('admin.batches.index', ['expiry' => 'expiring'])],
            ['key' => 'expired', 'tone' => 'danger', 'href' => route('admin.batches.index', ['expiry' => 'expired'])],
        ] as $card)
            <a href="{{ $card['href'] }}" class="card block p-4 transition hover:border-primary">
                <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.batches.summary.'.$card['key'], ['days' => $warningDays]) }}</p>
                <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-'.$card['tone'] => $card['tone'] && $summary[$card['key']] > 0])>{{ number_format($summary[$card['key']]) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="sm:col-span-2">
            <label for="batch-q" class="form-label">{{ __('admin.search') }}</label>
            <input id="batch-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.batches.search_placeholder') }}" class="form-control">
        </div>
        <x-ui.select name="status" :label="__('admin.status')" :options="\App\Enums\BatchStatus::options()" :value="$filters['status'] ?? null" :placeholder="__('admin.batches.all_active')" />
        <x-ui.select name="expiry" :label="__('admin.batches.expiry')" :options="['expiring' => __('admin.batches.summary.expiring', ['days' => $warningDays]), 'expired' => __('admin.batches.summary.expired')]" :value="$filters['expiry'] ?? null" :placeholder="__('admin.all')" />
        <x-ui.select name="storage" :label="__('admin.food_label.storage_type')" :options="\App\Enums\StorageType::options()" :value="$filters['storage'] ?? null" :placeholder="__('admin.all')" />
        <div class="flex justify-end gap-2 sm:col-span-2 lg:col-span-5">
            <x-ui.button variant="secondary" :href="route('admin.batches.index')">{{ __('admin.reset') }}</x-ui.button>
            <x-ui.button icon="filter">{{ __('admin.apply') }}</x-ui.button>
        </div>
    </form>

    @if ($batches->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="layers" :title="__('admin.batches.empty')" :description="__('admin.batches.empty_hint')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.batches.batch') }}</th>
                <th>{{ __('admin.products.product') }}</th>
                <th>{{ __('admin.batches.expires_at') }}</th>
                <th class="text-right">{{ __('admin.batches.quantity') }}</th>
                <th>{{ __('admin.batches.location') }}</th>
                <th>{{ __('admin.status') }}</th>
            </x-slot:head>
            @foreach ($batches as $batch)
                <tr>
                    <td>
                        <a href="{{ route('admin.batches.show', $batch) }}" class="font-mono text-sm font-medium hover:text-primary">{{ $batch->batch_number }}</a>
                        @if ($batch->lot_number)<p class="text-xs text-ink-muted">{{ __('admin.batches.lot') }} {{ $batch->lot_number }}</p>@endif
                    </td>
                    <td>
                        <p class="text-sm font-medium">{{ $batch->item?->variant?->product?->localizedName() ?? '—' }}</p>
                        <p class="text-xs text-ink-muted">@if ($batch->item?->variant?->translate('name')){{ $batch->item->variant->translate('name') }} · @endif<span class="font-mono">{{ $batch->item?->variant?->sku }}</span></p>
                    </td>
                    <td class="text-sm whitespace-nowrap">@include('admin.batches._expiry', ['date' => $batch->expires_at])</td>
                    <td class="text-right tabular-nums"><span class="font-semibold">{{ number_format($batch->quantity) }}</span><span class="text-ink-muted"> / {{ number_format($batch->initial_quantity) }}</span></td>
                    <td class="text-sm">{{ $batch->location ?: '—' }}</td>
                    <td><x-ui.badge :color="$batch->status->color()">{{ $batch->status->label() }}</x-ui.badge></td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($batches as $batch)
                    <a href="{{ route('admin.batches.show', $batch) }}" class="block space-y-1 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $batch->item?->variant?->product?->localizedName() ?? '—' }}</p>
                                <p class="font-mono text-xs text-ink-muted">{{ $batch->batch_number }}@if ($batch->lot_number) · {{ $batch->lot_number }}@endif</p>
                            </div>
                            <x-ui.badge :color="$batch->status->color()">{{ $batch->status->label() }}</x-ui.badge>
                        </div>
                        <p class="text-xs">@include('admin.batches._expiry', ['date' => $batch->expires_at]) · {{ number_format($batch->quantity) }} / {{ number_format($batch->initial_quantity) }}</p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>

        <div class="mt-4">{{ $batches->links() }}</div>
    @endif
</x-layouts.admin>
