@php
    $product = $item->variant?->product;
    $status = \App\Enums\StockStatus::for($sellable, $item->lowStockThreshold());
@endphp

<x-layouts.admin :title="$product?->localizedName() ?? __('admin.nav.inventory')">
    <x-ui.page-header :title="$product?->localizedName() ?? '—'" :back="route('admin.inventory.index')"
                      :description="trim(($item->variant?->translate('name') ? $item->variant->translate('name').' · ' : '').$item->variant?->sku)">
        <x-slot:actions>
            <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
            @if ($product && ! $product->trashed())
                @can('products.update')
                    <x-ui.button variant="secondary" size="sm" icon="pencil" :href="route('admin.products.edit', $product)">{{ __('admin.inventory.edit_product') }}</x-ui.button>
                @endcan
            @endif
            @can('inventory.receive')
                <x-ui.button size="sm" icon="plus" :href="route('admin.inventory.receive', $item)">{{ __('admin.inventory.receive') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.inventory.sellable') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($sellable) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.inventory.on_hand') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($item->quantity_on_hand) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.inventory.unsellable') }}</p>
            <p @class(['mt-1 text-2xl font-bold tabular-nums', 'text-warning' => $item->quantity_on_hand - $sellable > 0])>{{ number_format(max(0, $item->quantity_on_hand - $sellable)) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-medium text-ink-muted sm:text-sm">{{ __('admin.inventory.low_stock_threshold') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ number_format($item->lowStockThreshold()) }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($item->track_batches)
                <x-ui.card :title="__('admin.batches.title')" :description="__('admin.inventory.allocation_hint')" padding="p-0">
                    @if ($batches->isEmpty())
                        <x-ui.empty-state icon="layers" :title="__('admin.batches.empty')" :description="__('admin.inventory.receive_hint')">
                            @can('inventory.receive')
                                <x-ui.button icon="plus" :href="route('admin.inventory.receive', $item)">{{ __('admin.inventory.receive') }}</x-ui.button>
                            @endcan
                        </x-ui.empty-state>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($batches as $batch)
                                <li>
                                    <a href="{{ route('admin.batches.show', $batch) }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 hover:bg-surface-muted sm:px-6">
                                        <div class="min-w-0">
                                            <p class="font-mono text-sm font-medium">{{ $batch->batch_number }}</p>
                                            <p class="text-xs text-ink-muted">
                                                @if ($batch->lot_number){{ __('admin.batches.lot') }} {{ $batch->lot_number }} · @endif
                                                {{ $batch->location ?: __('admin.batches.no_location') }}
                                                @if ($batch->supplier) · {{ $batch->supplier->name }}@endif
                                            </p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                            <span class="whitespace-nowrap">@include('admin.batches._expiry', ['date' => $batch->expires_at])</span>
                                            <span class="tabular-nums"><span class="font-semibold">{{ number_format($batch->quantity) }}</span><span class="text-ink-muted"> / {{ number_format($batch->initial_quantity) }}</span></span>
                                            <x-ui.badge :color="$batch->status->color()">{{ $batch->status->label() }}</x-ui.badge>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($disposedCount > 0 || $showDisposed)
                        <div class="border-t border-line px-4 py-3 text-sm sm:px-6">
                            <a href="{{ route('admin.inventory.show', [$item, 'disposed' => $showDisposed ? null : 1]) }}" class="text-primary hover:underline">
                                {{ $showDisposed ? __('admin.inventory.hide_disposed') : __('admin.inventory.show_disposed', ['count' => $disposedCount]) }}
                            </a>
                        </div>
                    @endif
                </x-ui.card>
            @endif

            <x-ui.card :title="__('admin.movements.title')" padding="p-0">
                @include('admin.inventory._movements', ['movements' => $movements, 'showBatch' => $item->track_batches])
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            @can('inventory.adjust')
                @unless ($item->track_batches)
                    <x-ui.card :title="__('admin.inventory.adjust')">
                        <form method="POST" action="{{ route('admin.inventory.adjust', $item) }}" class="space-y-4">
                            @csrf
                            <x-ui.input name="delta" type="number" step="1" :label="__('admin.inventory.delta')" :hint="__('admin.inventory.delta_hint')" required />
                            <x-ui.input name="reason" :label="__('admin.inventory.reason')" required maxlength="250" />
                            <x-ui.button class="w-full">{{ __('admin.inventory.apply_adjustment') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @endunless

                <x-ui.card :title="__('admin.inventory.settings')">
                    <form method="POST" action="{{ route('admin.inventory.update', $item) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <x-ui.input name="low_stock_threshold" type="number" min="0" :label="__('admin.inventory.low_stock_threshold')" :value="$item->low_stock_threshold"
                                    :placeholder="(string) settings('inventory.low_stock_threshold')" :hint="__('admin.inventory.threshold_hint', ['default' => settings('inventory.low_stock_threshold')])" />
                        <x-ui.input name="location" :label="__('admin.inventory.default_location')" :value="$item->location" maxlength="60" :hint="__('admin.inventory.location_hint')" />
                        <x-ui.checkbox name="track_batches" :label="__('admin.inventory.track_batches')" :checked="$item->track_batches"
                                       :hint="$item->quantity_on_hand !== 0 ? __('admin.inventory.track_batches_locked') : __('admin.inventory.track_batches_hint')"
                                       :disabled="$item->quantity_on_hand !== 0" />
                        @if ($item->quantity_on_hand !== 0)
                            <input type="hidden" name="track_batches" value="{{ $item->track_batches ? 1 : 0 }}">
                        @endif
                        <x-ui.button variant="secondary" class="w-full">{{ __('shop.save') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </div>
    </div>
</x-layouts.admin>
