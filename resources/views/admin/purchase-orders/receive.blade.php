<x-layouts.admin :title="__('admin.purchase_orders.receive')">
    <x-ui.page-header :title="__('admin.purchase_orders.receive_title', ['number' => $order->reference()])" :back="route('admin.purchase-orders.show', $order)"
                      :description="__('admin.purchase_orders.receive_description', ['supplier' => $order->supplier?->name ?? '—'])" />

    <form method="POST" action="{{ route('admin.purchase-orders.receive.store', $order) }}" class="space-y-6">
        @csrf
        @error('lines')<x-ui.alert type="danger">{{ $message }}</x-ui.alert>@enderror

        @foreach ($order->items as $line)
            @php
                $remaining = $line->remainingQuantity();
                $tracked = $line->variant?->inventoryItem?->track_batches ?? true;
            @endphp
            <x-ui.card :title="$line->variant?->adminLabel() ?? '—'"
                       :description="__('admin.purchase_orders.received_progress', ['received' => number_format($line->quantity_received), 'ordered' => number_format($line->quantity)])">
                @if ($remaining === 0)
                    <p class="text-sm text-success">{{ __('admin.purchase_orders.line_complete') }}</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.input name="lines[{{ $line->id }}][quantity]" type="number" min="0" :max="$remaining" step="1" :value="$remaining"
                                    :label="__('admin.purchase_orders.receive_quantity')" :hint="__('admin.purchase_orders.remaining', ['count' => number_format($remaining)])" />
                        @if ($tracked)
                            <x-ui.input name="lines[{{ $line->id }}][expires_at]" type="date" :min="local_today()->toDateString()" :label="__('admin.batches.expires_at')" :hint="__('admin.purchase_orders.expiry_required_hint')" />
                            <x-ui.input name="lines[{{ $line->id }}][lot_number]" :label="__('admin.batches.lot_number')" maxlength="60" />
                            <x-ui.input name="lines[{{ $line->id }}][manufactured_at]" type="date" :max="local_today()->toDateString()" :label="__('admin.batches.manufactured_at')" />
                            <x-ui.input name="lines[{{ $line->id }}][batch_number]" :label="__('admin.batches.batch_number')" maxlength="40" class="uppercase" :hint="__('admin.batches.batch_number_hint')" />
                            <x-ui.input name="lines[{{ $line->id }}][location]" :label="__('admin.batches.location')" :value="$line->variant?->inventoryItem?->location" maxlength="60" />
                        @else
                            <p class="text-sm text-ink-muted sm:col-span-1 lg:col-span-3 lg:pt-7">{{ __('admin.purchase_orders.untracked_line') }}</p>
                        @endif
                    </div>
                @endif
            </x-ui.card>
        @endforeach

        <p class="text-sm text-ink-muted">{{ __('admin.purchase_orders.receive_hint') }}</p>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="secondary" :href="route('admin.purchase-orders.show', $order)">{{ __('shop.cancel') }}</x-ui.button>
            <x-ui.button icon="download">{{ __('admin.purchase_orders.receive') }}</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
