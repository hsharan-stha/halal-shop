{{-- Stock ledger. Expects $movements (paginator) and $showBatch (bool). --}}
@if ($movements->isEmpty())
    <p class="p-4 text-sm text-ink-muted sm:px-6">{{ __('admin.movements.empty') }}</p>
@else
    <div class="hidden overflow-x-auto md:block">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('admin.movements.date') }}</th>
                    <th>{{ __('admin.movements.type') }}</th>
                    <th class="text-right">{{ __('admin.movements.quantity') }}</th>
                    <th class="text-right">{{ __('admin.movements.balance') }}</th>
                    @if ($showBatch)<th>{{ __('admin.batches.batch') }}</th>@endif
                    <th>{{ __('admin.movements.details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td class="text-sm whitespace-nowrap">{{ local_date($movement->created_at, true) }}</td>
                        <td><x-ui.badge :color="$movement->type->color()">{{ $movement->type->label() }}</x-ui.badge></td>
                        <td @class(['text-right font-semibold tabular-nums', 'text-success' => $movement->quantity > 0, 'text-danger' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}</td>
                        <td class="text-right text-sm text-ink-muted tabular-nums whitespace-nowrap">{{ number_format($movement->before_quantity) }} → {{ number_format($movement->after_quantity) }}</td>
                        @if ($showBatch)
                            <td class="text-sm">
                                @if ($movement->batch)
                                    <a href="{{ route('admin.batches.show', $movement->batch) }}" class="font-mono hover:text-primary">{{ $movement->batch->batch_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        <td class="text-sm">@include('admin.inventory._movement_details')</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <ul class="divide-y divide-line md:hidden">
        @foreach ($movements as $movement)
            <li class="space-y-1 px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <x-ui.badge :color="$movement->type->color()">{{ $movement->type->label() }}</x-ui.badge>
                    <span @class(['font-semibold tabular-nums', 'text-success' => $movement->quantity > 0, 'text-danger' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}</span>
                </div>
                <p class="text-xs text-ink-muted">
                    {{ local_date($movement->created_at, true) }} · {{ number_format($movement->before_quantity) }} → {{ number_format($movement->after_quantity) }}
                    @if ($showBatch && $movement->batch) · <span class="font-mono">{{ $movement->batch->batch_number }}</span>@endif
                </p>
                <div class="text-xs">@include('admin.inventory._movement_details')</div>
            </li>
        @endforeach
    </ul>
    @if ($movements->hasPages())
        <div class="border-t border-line p-4">{{ $movements->links() }}</div>
    @endif
@endif
