@use('App\Enums\BatchStatus')
@use('App\Enums\InventoryMovementType')

@php
    $variant = $batch->item?->variant;
    $product = $variant?->product;
    $isOpen = $batch->status !== BatchStatus::Disposed;
@endphp

<x-layouts.admin :title="$batch->batch_number">
    <x-ui.page-header :title="$batch->batch_number" :back="$batch->item ? route('admin.inventory.show', $batch->item) : route('admin.batches.index')"
                      :description="($product?->localizedName() ?? '—').($variant?->translate('name') ? ' · '.$variant->translate('name') : '').' ('.$variant?->sku.')'">
        <x-slot:actions>
            <x-ui.badge :color="$batch->status->color()">{{ $batch->status->label() }}</x-ui.badge>
            @if ($batch->isAllocatable())
                <x-ui.badge color="success" icon="check">{{ __('admin.batches.sellable') }}</x-ui.badge>
            @elseif ($isOpen && $batch->quantity > 0)
                <x-ui.badge color="warning" icon="alert">{{ __('admin.batches.not_sellable') }}</x-ui.badge>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($batch->isExpired() && $batch->quantity > 0 && $isOpen)
        <x-ui.alert type="danger" class="mb-6">{{ __('admin.batches.expired_warning') }}</x-ui.alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.batches.details')">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-muted">{{ __('admin.batches.quantity') }}</dt><dd class="text-lg font-semibold tabular-nums">{{ number_format($batch->quantity) }} <span class="text-sm font-normal text-ink-muted">/ {{ number_format($batch->initial_quantity) }}</span></dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.expires_at') }}</dt><dd>@include('admin.batches._expiry', ['date' => $batch->expires_at])</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.lot_number') }}</dt><dd class="font-mono">{{ $batch->lot_number ?: '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.manufactured_at') }}</dt><dd>{{ $batch->manufactured_at ? local_date($batch->manufactured_at) : '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.received_at') }}</dt><dd>{{ local_date($batch->received_at) }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.location') }}</dt><dd>{{ $batch->location ?: '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.food_label.storage_type') }}</dt><dd><x-ui.badge :color="$batch->storage_type->color()">{{ $batch->storage_type->label() }}</x-ui.badge></dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.batches.unit_cost') }}</dt><dd class="tabular-nums">{{ $batch->unit_cost !== null ? money($batch->unit_cost) : '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.purchase_orders.supplier') }}</dt><dd>{{ $batch->supplier?->name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-ink-muted">{{ __('admin.purchase_orders.title') }}</dt>
                        <dd>
                            @if ($order = $batch->purchaseOrderItem?->purchaseOrder)
                                @can('purchase_orders.view')
                                    <a href="{{ route('admin.purchase-orders.show', $order) }}" class="font-mono text-primary hover:underline">{{ $order->reference() }}</a>
                                @else
                                    <span class="font-mono">{{ $order->reference() }}</span>
                                @endcan
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    @if ($batch->status_reason)
                        <div class="sm:col-span-2"><dt class="text-ink-muted">{{ __('admin.batches.status_reason') }}</dt><dd>{{ $batch->status_reason }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('admin.movements.title')" padding="p-0">
                @include('admin.inventory._movements', ['movements' => $movements, 'showBatch' => false])
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            @can('inventory.adjust')
                @if ($isOpen)
                    <x-ui.card :title="__('admin.batches.status_card')">
                        <div class="space-y-3">
                            @if ($batch->status === BatchStatus::Available)
                                <form method="POST" action="{{ route('admin.batches.status', $batch) }}" class="space-y-2">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ BatchStatus::Quarantined->value }}">
                                    <x-ui.input name="reason" id="quarantine-reason" :label="__('admin.batches.quarantine_reason')" required maxlength="250" />
                                    <x-ui.button variant="secondary" icon="lock" class="w-full">{{ __('admin.batches.quarantine') }}</x-ui.button>
                                </form>
                            @elseif ($batch->status === BatchStatus::Quarantined && ! $batch->isExpired())
                                <x-ui.confirm-form :action="route('admin.batches.status', $batch)" :message="__('admin.batches.confirm_release')">
                                    <input type="hidden" name="status" value="{{ BatchStatus::Available->value }}">
                                    <p class="form-hint mb-2">{{ __('admin.batches.release_hint') }}</p>
                                    <x-ui.button icon="check" class="w-full">{{ __('admin.batches.release') }}</x-ui.button>
                                </x-ui.confirm-form>
                            @endif
                            @if ($batch->status !== BatchStatus::Expired)
                                <x-ui.confirm-form :action="route('admin.batches.status', $batch)" :message="__('admin.batches.confirm_mark_expired')">
                                    <input type="hidden" name="status" value="{{ BatchStatus::Expired->value }}">
                                    <x-ui.button variant="ghost" icon="clock" class="w-full text-danger">{{ __('admin.batches.mark_expired') }}</x-ui.button>
                                </x-ui.confirm-form>
                            @else
                                <p class="text-sm text-ink-muted">{{ __('admin.batches.expired_hint') }}</p>
                            @endif
                        </div>
                    </x-ui.card>

                    @if ($batch->quantity > 0)
                        <x-ui.card :title="__('admin.batches.dispose')">
                            <x-ui.confirm-form :action="route('admin.batches.dispose', $batch)" :message="__('admin.batches.confirm_dispose')" class="space-y-3">
                                <x-ui.input name="quantity" id="dispose-quantity" type="number" min="1" :max="$batch->quantity" :value="$batch->quantity" :label="__('admin.batches.quantity')" required />
                                <x-ui.select name="type" id="dispose-type" :label="__('admin.batches.dispose_type')" required
                                             :value="$batch->isExpired() ? InventoryMovementType::Expiry->value : InventoryMovementType::Damage->value"
                                             :options="collect(InventoryMovementType::disposals())->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" />
                                <x-ui.input name="reason" id="dispose-reason" :label="__('admin.inventory.reason')" required maxlength="250" />
                                <x-ui.button variant="danger" icon="trash" class="w-full">{{ __('admin.batches.dispose') }}</x-ui.button>
                            </x-ui.confirm-form>
                        </x-ui.card>

                        <x-ui.card :title="__('admin.batches.transfer')">
                            <form method="POST" action="{{ route('admin.batches.transfer', $batch) }}" class="space-y-3">
                                @csrf
                                <x-ui.input name="location" id="transfer-location" :label="__('admin.batches.new_location')" required maxlength="60" />
                                <x-ui.input name="quantity" id="transfer-quantity" type="number" min="1" :max="$batch->quantity" :value="$batch->quantity" :label="__('admin.batches.quantity')" :hint="__('admin.batches.transfer_hint')" required />
                                <x-ui.input name="reason" id="transfer-reason" :label="__('admin.inventory.reason')" maxlength="200" />
                                <x-ui.button variant="secondary" icon="truck" class="w-full">{{ __('admin.batches.transfer') }}</x-ui.button>
                            </form>
                        </x-ui.card>
                    @endif

                    <x-ui.card :title="__('admin.batches.adjust')">
                        <form method="POST" action="{{ route('admin.batches.adjust', $batch) }}" class="space-y-3">
                            @csrf
                            <x-ui.input name="delta" id="adjust-delta" type="number" step="1" :label="__('admin.inventory.delta')" :hint="__('admin.inventory.delta_hint')" required />
                            <x-ui.input name="reason" id="adjust-reason" :label="__('admin.inventory.reason')" required maxlength="250" />
                            <x-ui.button variant="secondary" class="w-full">{{ __('admin.inventory.apply_adjustment') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @else
                    <x-ui.card>
                        <p class="text-sm text-ink-muted">{{ __('admin.batches.disposed_hint') }}</p>
                    </x-ui.card>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.admin>
