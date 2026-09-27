<x-layouts.admin :title="__('admin.halal_shops.title')">
    <x-ui.page-header :title="__('admin.halal_shops.title')" :description="__('admin.halal_shops.description')">
        <x-slot:actions>
            <x-ui.button :href="route('admin.halal-shops.create')" icon="plus">{{ __('admin.halal_shops.create') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($shops->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building" :title="__('admin.halal_shops.empty')" :description="__('admin.halal_shops.empty_hint')" />
        </x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th>{{ __('admin.halal_shops.name') }}</th>
                <th>{{ __('shop.checkout.prefecture') }}</th>
                <th>{{ __('admin.nav.products') }}</th>
                <th>{{ __('admin.nav.orders') }}</th>
                <th>{{ __('admin.status') }}</th>
                <th class="text-right">{{ __('admin.actions') }}</th>
            </x-slot:head>
            @foreach ($shops as $shop)
                <tr>
                    <td>
                        <p class="font-medium">{{ $shop->name }}</p>
                        <p class="text-xs text-ink-muted">{{ $shop->summary() }}</p>
                    </td>
                    <td>{{ \App\Support\Prefectures::label($shop->prefecture) }}</td>
                    <td>{{ $shop->products_count }}</td>
                    <td>{{ $shop->orders_count }}</td>
                    <td><x-ui.badge :color="$shop->is_active ? 'success' : 'neutral'">{{ $shop->is_active ? __('admin.halal_shops.active') : __('admin.halal_shops.inactive') }}</x-ui.badge></td>
                    <td class="text-right">
                        <x-ui.button :href="route('admin.halal-shops.edit', $shop)" variant="ghost" size="sm">{{ __('admin.edit') }}</x-ui.button>
                    </td>
                </tr>
            @endforeach
            <x-slot:mobile>
                @foreach ($shops as $shop)
                    <a href="{{ route('admin.halal-shops.edit', $shop) }}" class="block space-y-1 p-4">
                        <p class="font-medium">{{ $shop->name }}</p>
                        <p class="text-sm text-ink-muted">{{ $shop->summary() }}</p>
                    </a>
                @endforeach
            </x-slot:mobile>
        </x-ui.table>
        <div class="mt-4">{{ $shops->links() }}</div>
    @endif
</x-layouts.admin>
