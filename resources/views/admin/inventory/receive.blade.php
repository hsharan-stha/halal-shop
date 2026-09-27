@php($product = $item->variant?->product)

<x-layouts.admin :title="__('admin.inventory.receive')">
    <x-ui.page-header :title="__('admin.inventory.receive')" :back="route('admin.inventory.show', $item)"
                      :description="($product?->localizedName() ?? '—').($item->variant?->translate('name') ? ' · '.$item->variant->translate('name') : '').' ('.$item->variant?->sku.')'" />

    <form method="POST" action="{{ route('admin.inventory.receive.store', $item) }}" class="grid gap-6 lg:grid-cols-3">
        @csrf

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.inventory.receipt_details')">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.input name="quantity" type="number" min="1" step="1" :label="__('admin.inventory.quantity')" required autofocus />
                    <x-ui.select name="supplier_id" :label="__('admin.purchase_orders.supplier')" :options="$suppliers" :value="$defaults['supplier_id']" :placeholder="__('admin.none')" />
                    <x-ui.input name="unit_cost" type="number" min="0" step="1" :label="__('admin.batches.unit_cost')" :value="$defaults['unit_cost']" :hint="__('admin.batches.unit_cost_hint')" />
                    <x-ui.input name="received_at" type="date" :label="__('admin.batches.received_at')" :value="local_today()->toDateString()" :max="local_today()->toDateString()" />
                </div>
            </x-ui.card>

            @if ($item->track_batches)
                <x-ui.card :title="__('admin.inventory.batch_information')" :description="__('admin.inventory.batch_information_hint')">
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-ui.input name="expires_at" type="date" :label="__('admin.batches.expires_at')" :min="local_today()->toDateString()" required :hint="__('admin.batches.expires_at_hint')" />
                        <x-ui.input name="manufactured_at" type="date" :label="__('admin.batches.manufactured_at')" :max="local_today()->toDateString()" />
                        <x-ui.input name="lot_number" :label="__('admin.batches.lot_number')" maxlength="60" :hint="__('admin.batches.lot_number_hint')" />
                        <x-ui.input name="batch_number" :label="__('admin.batches.batch_number')" maxlength="40" class="uppercase" :hint="__('admin.batches.batch_number_hint')" />
                        <x-ui.input name="location" :label="__('admin.batches.location')" :value="$item->location" maxlength="60" :hint="__('admin.inventory.location_hint')" />
                        <div class="md:pt-7">
                            <x-ui.checkbox name="quarantine" :label="__('admin.inventory.receive_quarantined')" :hint="__('admin.inventory.receive_quarantined_hint')" />
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.inventory.reason')">
                <x-ui.textarea name="reason" :rows="3" maxlength="250" :hint="__('admin.inventory.receive_reason_hint')" />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('admin.inventory.show', $item)">{{ __('shop.cancel') }}</x-ui.button>
                <x-ui.button icon="plus">{{ __('admin.inventory.receive') }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.admin>
