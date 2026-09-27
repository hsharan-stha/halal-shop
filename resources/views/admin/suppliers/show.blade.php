<x-layouts.admin :title="$supplier->name">
    <x-ui.page-header :title="$supplier->name" :back="route('admin.suppliers.index')" :description="$supplier->company_name">
        <x-slot:actions>
            <x-ui.badge :color="$supplier->is_active ? 'success' : 'neutral'">{{ $supplier->is_active ? __('admin.active') : __('admin.inactive') }}</x-ui.badge>
            @can('suppliers.manage')
                <x-ui.button variant="secondary" size="sm" icon="pencil" :href="route('admin.suppliers.edit', $supplier)">{{ __('admin.edit') }}</x-ui.button>
            @endcan
            @can('purchase_orders.manage')
                @if ($supplier->is_active)
                    <x-ui.button size="sm" icon="plus" :href="route('admin.purchase-orders.create', ['supplier' => $supplier->id])">{{ __('admin.purchase_orders.create') }}</x-ui.button>
                @endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-ui.card :title="__('admin.suppliers.products')" :description="__('admin.suppliers.products_hint')" padding="p-0">
                @if ($supplier->supplierProducts->isEmpty())
                    <p class="p-4 text-sm text-ink-muted sm:px-6">{{ __('admin.suppliers.no_products') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($supplier->supplierProducts as $supplierProduct)
                            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 sm:px-6">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">
                                        {{ $supplierProduct->variant?->adminLabel() ?? '—' }}
                                        @if ($supplierProduct->is_preferred)<x-ui.badge color="success" class="ml-1">{{ __('admin.suppliers.preferred') }}</x-ui.badge>@endif
                                        @if ($supplierProduct->variant?->trashed())<x-ui.badge color="neutral" class="ml-1">{{ __('admin.suppliers.variant_deleted') }}</x-ui.badge>@endif
                                    </p>
                                    <p class="text-xs text-ink-muted">
                                        @if ($supplierProduct->supplier_sku){{ __('admin.suppliers.supplier_sku') }}: <span class="font-mono">{{ $supplierProduct->supplier_sku }}</span> · @endif
                                        {{ __('admin.batches.unit_cost') }}: {{ $supplierProduct->unit_cost !== null ? money($supplierProduct->unit_cost) : '—' }}
                                        @if ($supplierProduct->lead_time_days !== null) · {{ trans_choice('admin.suppliers.lead_time_value', $supplierProduct->lead_time_days, ['count' => $supplierProduct->lead_time_days]) }}@endif
                                        @if ($supplierProduct->min_order_quantity) · {{ __('admin.suppliers.moq_value', ['count' => $supplierProduct->min_order_quantity]) }}@endif
                                    </p>
                                </div>
                                @can('suppliers.manage')
                                    <x-ui.confirm-form :action="route('admin.suppliers.products.destroy', [$supplier, $supplierProduct])" method="DELETE" :message="__('admin.suppliers.confirm_remove_product')">
                                        <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
                                    </x-ui.confirm-form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('suppliers.manage')
                    <form method="POST" action="{{ route('admin.suppliers.products.store', $supplier) }}" class="space-y-4 border-t border-line p-4 sm:px-6">
                        @csrf
                        <p class="text-sm font-semibold">{{ __('admin.suppliers.add_product') }}</p>
                        @if (empty($variantOptions))
                            <p class="text-sm text-ink-muted">{{ __('admin.suppliers.all_products_linked') }}</p>
                        @else
                            <div class="grid gap-4 md:grid-cols-2">
                                <x-ui.select name="product_variant_id" :label="__('admin.purchase_orders.product')" :options="$variantOptions" :placeholder="__('admin.choose')" required wrapperClass="md:col-span-2" />
                                <x-ui.input name="supplier_sku" :label="__('admin.suppliers.supplier_sku')" maxlength="64" />
                                <x-ui.input name="unit_cost" type="number" min="0" step="1" :label="__('admin.batches.unit_cost')" :hint="__('admin.batches.unit_cost_hint')" />
                                <x-ui.input name="lead_time_days" type="number" min="0" max="365" :label="__('admin.suppliers.lead_time_days')" />
                                <x-ui.input name="min_order_quantity" type="number" min="1" :label="__('admin.suppliers.min_order_quantity')" />
                                <x-ui.checkbox name="is_preferred" :label="__('admin.suppliers.preferred')" :hint="__('admin.suppliers.preferred_hint')" class="md:col-span-2" />
                            </div>
                            <div class="flex justify-end">
                                <x-ui.button variant="secondary" icon="plus">{{ __('admin.suppliers.add_product') }}</x-ui.button>
                            </div>
                        @endif
                    </form>
                @endcan
            </x-ui.card>

            <x-ui.card :title="__('admin.suppliers.recent_orders')" padding="p-0">
                <x-slot:actions>
                    @can('purchase_orders.view')
                        <x-ui.button variant="ghost" size="sm" :href="route('admin.purchase-orders.index', ['supplier' => $supplier->id])">{{ __('admin.view_all') }}</x-ui.button>
                    @endcan
                </x-slot:actions>
                @if ($purchaseOrders->isEmpty())
                    <p class="p-4 text-sm text-ink-muted sm:px-6">{{ __('admin.purchase_orders.empty') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($purchaseOrders as $order)
                            <li>
                                <a href="{{ route('admin.purchase-orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-surface-muted sm:px-6">
                                    <div>
                                        <p class="font-mono text-sm font-medium">{{ $order->reference() }}</p>
                                        <p class="text-xs text-ink-muted">{{ local_date($order->created_at) }} · {{ trans_choice('admin.purchase_orders.line_count', $order->items_count, ['count' => $order->items_count]) }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm tabular-nums">{{ money($order->subtotal) }}</span>
                                        <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('admin.suppliers.contact')">
                <dl class="space-y-3 text-sm">
                    @if ($supplier->code)
                        <div><dt class="text-ink-muted">{{ __('admin.suppliers.code') }}</dt><dd class="font-mono">{{ $supplier->code }}</dd></div>
                    @endif
                    <div><dt class="text-ink-muted">{{ __('admin.suppliers.contact_name') }}</dt><dd>{{ $supplier->contact_name ?: '—' }}</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.suppliers.email') }}</dt><dd class="break-all">@if ($supplier->email)<a href="mailto:{{ $supplier->email }}" class="text-primary hover:underline">{{ $supplier->email }}</a>@else — @endif</dd></div>
                    <div><dt class="text-ink-muted">{{ __('admin.suppliers.phone') }}</dt><dd>@if ($supplier->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $supplier->phone) }}" class="text-primary hover:underline">{{ $supplier->phone }}</a>@else — @endif</dd></div>
                    <div>
                        <dt class="text-ink-muted">{{ __('admin.suppliers.address') }}</dt>
                        <dd>
                            @if ($supplier->postal_code)〒{{ $supplier->postal_code }}<br>@endif
                            {{ $supplier->country_code === 'JP' ? \App\Support\Prefectures::label($supplier->prefecture) : $supplier->prefecture }} {{ $supplier->address }}
                            <br><span class="text-ink-muted">{{ country_name($supplier->country_code) }}</span>
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            @if ($supplier->notes)
                <x-ui.card :title="__('admin.suppliers.notes')">
                    <p class="text-sm whitespace-pre-line">{{ $supplier->notes }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.admin>
