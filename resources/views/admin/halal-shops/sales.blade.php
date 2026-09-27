<x-layouts.admin :title="__('admin.halal_shops.sales')">
    <x-ui.page-header :title="__('admin.halal_shops.sales')" :description="__('admin.halal_shops.sales_hint')" />

    @if ($canEditCommission)
        <x-ui.card class="mb-4">
            <form method="POST" action="{{ route('admin.shop-sales.commission') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <x-ui.input name="commission_percent" type="number" min="0" max="100" :label="__('admin.halal_shops.commission')" :value="$commissionPercent" required wrapper-class="w-40" />
                <x-ui.button>{{ __('shop.save') }}</x-ui.button>
            </form>
        </x-ui.card>
    @else
        <p class="mb-4 text-sm text-ink-muted">{{ __('admin.halal_shops.commission_rate', ['percent' => $commissionPercent]) }}</p>
    @endif

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.halal_shops.name') }}</th>
            <th>{{ __('admin.halal_shops.order_count') }}</th>
            <th>{{ __('admin.halal_shops.sales_amount') }}</th>
            <th>{{ __('admin.halal_shops.commission_amount') }}</th>
        </x-slot:head>
        @forelse ($shops as $shop)
            <tr>
                <td class="font-medium">{{ $shop->name }}</td>
                <td>{{ $shop->orders_count }}</td>
                <td class="tabular-nums">{{ money((int) $shop->sales_total) }}</td>
                <td class="tabular-nums">{{ money((int) $shop->commission_total) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-sm text-ink-muted">{{ __('admin.halal_shops.no_sales') }}</td>
            </tr>
        @endforelse
        <x-slot:mobile>
            @forelse ($shops as $shop)
                <div class="space-y-1 p-4">
                    <p class="font-medium">{{ $shop->name }}</p>
                    <p class="text-sm text-ink-muted">{{ $shop->orders_count }} · {{ money((int) $shop->sales_total) }} · {{ money((int) $shop->commission_total) }}</p>
                </div>
            @empty
                <p class="p-4 text-sm text-ink-muted">{{ __('admin.halal_shops.no_sales') }}</p>
            @endforelse
        </x-slot:mobile>
    </x-ui.table>
</x-layouts.admin>
