<x-layouts.admin :title="__('admin.nav.tax')">
    <x-ui.page-header :title="__('admin.tax.title')" :description="__('admin.tax.description')">
        <x-slot:actions>
            @can('tax.manage')
                <x-ui.button :href="route('admin.tax.create')" icon="plus">{{ __('admin.tax.create') }}</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table>
        <x-slot:head>
            <th>{{ __('admin.name_ja') }}</th>
            <th>{{ __('admin.tax.code') }}</th>
            <th>{{ __('admin.tax.current_rate') }}</th>
            <th class="text-right">{{ __('admin.tax.products') }}</th>
            <th>{{ __('admin.tax.is_default') }}</th>
            @can('tax.manage')
                <th class="text-right">{{ __('admin.actions') }}</th>
            @endcan
        </x-slot:head>
        @foreach ($classes as $taxClass)
            <tr>
                <td class="font-medium">{{ $taxClass->translate('name') }}</td>
                <td class="font-mono text-xs text-ink-muted">{{ $taxClass->code }}</td>
                <td class="tabular-nums">{{ $taxClass->rateOn()?->percentage() ?? __('admin.tax.no_rate') }}</td>
                <td class="text-right tabular-nums">{{ number_format($taxClass->products_count) }}</td>
                <td>
                    @if ($taxClass->is_default)
                        <x-ui.badge color="primary">{{ __('admin.tax.is_default') }}</x-ui.badge>
                    @endif
                </td>
                @can('tax.manage')
                    <td class="text-right">
                        <div class="inline-flex shrink-0 items-center gap-1">
                            <x-ui.button variant="ghost" size="sm" :href="route('admin.tax.edit', $taxClass)" icon="pencil" :aria-label="__('admin.edit')" />
                            <x-ui.confirm-form :action="route('admin.tax.destroy', $taxClass)" method="DELETE" :message="__('admin.tax.confirm_delete')">
                                <x-ui.button variant="ghost" size="sm" icon="trash" class="text-danger" :aria-label="__('admin.delete')" />
                            </x-ui.confirm-form>
                        </div>
                    </td>
                @endcan
            </tr>
        @endforeach
        <x-slot:mobile>
            @foreach ($classes as $taxClass)
                <div class="flex items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="font-medium">{{ $taxClass->translate('name') }}</p>
                        <p class="text-xs text-ink-muted">{{ $taxClass->code }} · {{ $taxClass->rateOn()?->percentage() ?? __('admin.tax.no_rate') }}</p>
                    </div>
                    @can('tax.manage')
                        <x-ui.button variant="ghost" size="sm" :href="route('admin.tax.edit', $taxClass)" icon="pencil" :aria-label="__('admin.edit')" />
                    @endcan
                </div>
            @endforeach
        </x-slot:mobile>
    </x-ui.table>
</x-layouts.admin>
