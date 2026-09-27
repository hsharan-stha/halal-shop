@php
    $title = $order->exists ? __('admin.purchase_orders.edit', ['number' => $order->reference()]) : __('admin.purchase_orders.create');
    $initialLines = collect($lines)->map(fn ($line) => [
        'product_variant_id' => (string) ($line['product_variant_id'] ?? ''),
        'quantity' => (string) ($line['quantity'] ?? ''),
        'unit_cost' => (string) ($line['unit_cost'] ?? ''),
    ])->values()->all();
    $lineErrors = collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'lines.'))->map(fn ($messages) => $messages[0])->all();
@endphp

<x-layouts.admin :title="$title">
    <x-ui.page-header :title="$title" :back="$order->exists ? route('admin.purchase-orders.show', $order) : route('admin.purchase-orders.index')" />

    @if (empty($suppliers))
        <x-ui.card>
            <x-ui.empty-state icon="building" :title="__('admin.purchase_orders.no_suppliers')" :description="__('admin.purchase_orders.no_suppliers_hint')">
                @can('suppliers.manage')
                    <x-ui.button icon="plus" :href="route('admin.suppliers.create')">{{ __('admin.suppliers.create') }}</x-ui.button>
                @endcan
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <form method="POST" action="{{ $order->exists ? route('admin.purchase-orders.update', $order) : route('admin.purchase-orders.store') }}"
              class="grid gap-6 lg:grid-cols-3"
              x-data="purchaseOrderForm({
                  supplier: @js((string) old('supplier_id', $order->supplier_id ?? '')),
                  lines: @js($initialLines),
                  costs: @js($supplierCosts),
                  errors: @js($lineErrors),
                  locale: @js(str_replace('_', '-', app()->getLocale())),
                  currency: @js(config('shop.currency')),
              })">
            @csrf
            @if ($order->exists)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-ui.card :title="__('admin.purchase_orders.lines')" :description="__('admin.purchase_orders.lines_hint')" padding="p-0">
                    @error('lines')<p class="form-error px-4 pt-4 sm:px-6">{{ $message }}</p>@enderror

                    <ul class="divide-y divide-line">
                        <template x-for="(line, index) in lines" :key="line.key">
                            <li class="grid gap-3 px-4 py-4 sm:px-6 md:grid-cols-12 md:items-start">
                                <div class="md:col-span-6">
                                    <label :for="'line-variant-' + index" class="form-label">{{ __('admin.purchase_orders.product') }}</label>
                                    <select :id="'line-variant-' + index" :name="'lines[' + index + '][product_variant_id]'" class="form-control pr-8"
                                            x-model="line.product_variant_id" @change="applyCost(line)" :aria-invalid="error(index, 'product_variant_id') ? 'true' : null">
                                        <option value="">{{ __('admin.choose') }}</option>
                                        @foreach ($variantOptions as $variantId => $variantLabel)
                                            <option value="{{ $variantId }}">{{ $variantLabel }}</option>
                                        @endforeach
                                    </select>
                                    <p class="form-error" x-show="error(index, 'product_variant_id')" x-text="error(index, 'product_variant_id')"></p>
                                </div>
                                <div class="md:col-span-2">
                                    <label :for="'line-quantity-' + index" class="form-label">{{ __('admin.purchase_orders.quantity') }}</label>
                                    <input :id="'line-quantity-' + index" :name="'lines[' + index + '][quantity]'" type="number" min="1" max="{{ \App\Http\Requests\Admin\PurchaseOrderRequest::MAX_QUANTITY }}" step="1"
                                           class="form-control" x-model="line.quantity" :aria-invalid="error(index, 'quantity') ? 'true' : null">
                                    <p class="form-error" x-show="error(index, 'quantity')" x-text="error(index, 'quantity')"></p>
                                </div>
                                <div class="md:col-span-3">
                                    <label :for="'line-cost-' + index" class="form-label">{{ __('admin.purchase_orders.unit_cost') }}</label>
                                    <input :id="'line-cost-' + index" :name="'lines[' + index + '][unit_cost]'" type="number" min="0" max="{{ \App\Http\Requests\Admin\PurchaseOrderRequest::MAX_UNIT_COST }}" step="1"
                                           class="form-control" x-model="line.unit_cost" @input="line.costTouched = true" :aria-invalid="error(index, 'unit_cost') ? 'true' : null">
                                    <p class="form-error" x-show="error(index, 'unit_cost')" x-text="error(index, 'unit_cost')"></p>
                                    <p class="form-hint" x-show="line.product_variant_id" x-text="formatMoney(lineTotal(line))"></p>
                                </div>
                                <div class="flex justify-end md:col-span-1 md:pt-7">
                                    <button type="button" class="btn btn-ghost btn-sm text-danger" @click="removeLine(index)" aria-label="{{ __('admin.purchase_orders.remove_line') }}">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 sm:px-6">
                        <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addLine()">{{ __('admin.purchase_orders.add_line') }}</x-ui.button>
                        <p class="text-sm">
                            {{ __('admin.purchase_orders.subtotal') }}:
                            <span class="font-semibold tabular-nums" x-text="formatMoney(subtotal())"></span>
                        </p>
                    </div>
                </x-ui.card>
            </div>

            <div class="min-w-0 space-y-6">
                <x-ui.card :title="__('admin.purchase_orders.details')">
                    <div class="space-y-4">
                        <x-ui.select name="supplier_id" :label="__('admin.purchase_orders.supplier')" :options="$suppliers" :value="$order->supplier_id"
                                     :placeholder="__('admin.choose')" required x-model="supplier" />
                        <x-ui.input name="expected_at" type="date" :label="__('admin.purchase_orders.expected_at')" :value="$order->expected_at?->toDateString()" />
                        <x-ui.textarea name="notes" :label="__('admin.purchase_orders.notes')" :value="$order->notes" :rows="4" maxlength="5000" />
                    </div>
                </x-ui.card>

                <p class="text-sm text-ink-muted">{{ __('admin.purchase_orders.draft_hint') }}</p>

                <div class="flex justify-end gap-2">
                    <x-ui.button variant="secondary" :href="$order->exists ? route('admin.purchase-orders.show', $order) : route('admin.purchase-orders.index')">{{ __('shop.cancel') }}</x-ui.button>
                    <x-ui.button>{{ __('admin.purchase_orders.save_draft') }}</x-ui.button>
                </div>
            </div>
        </form>
    @endif
</x-layouts.admin>
