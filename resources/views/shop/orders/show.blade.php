<x-layouts.account :title="$order->order_number">
    <p class="text-sm"><a href="{{ route('account.orders.index') }}" class="text-ink-muted hover:text-ink">{{ __('shop.orders.title') }}</a></p>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        <h1 class="font-mono text-2xl font-bold">{{ $order->order_number }}</h1>
        <x-ui.badge :color="$order->status->color()">{{ $order->status->label() }}</x-ui.badge>
        <x-ui.badge :color="$order->payment_status->color()">{{ $order->payment_status->label() }}</x-ui.badge>
    </div>
    <p class="mt-1 text-sm text-ink-muted">{{ local_date($order->placed_at, true) }}</p>

    @if ($order->payment_status->value === 'unpaid')
        <p class="mt-4 rounded-xl border border-line bg-surface-muted p-4 text-sm">{{ __('shop.checkout.pay_later') }}</p>
    @endif

    @if ($order->payment_method->value === 'bank_transfer' && $bankInstructions !== '')
        <section class="card mt-4 p-4 sm:p-6">
            <h2 class="text-lg font-semibold">{{ __('shop.checkout.bank_instructions') }}</h2>
            <p class="mt-2 text-sm whitespace-pre-line">{{ $bankInstructions }}</p>
        </section>
    @endif

    @if ($order->payment_method->value === 'cash_on_delivery' && $order->payment_status->value === 'unpaid')
        <p class="mt-4 text-sm text-ink-muted">{{ __('shop.checkout.cod_notice') }}</p>
    @endif

    <section class="card mt-6 p-4 sm:p-6">
        <h2 class="text-lg font-semibold">{{ __('shop.orders.detail') }}</h2>
        <ul class="mt-3 divide-y divide-line">
            @foreach ($order->items as $item)
                <li class="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
                    <span class="min-w-0">
                        <span class="block font-medium">{{ $item->product_name }}</span>
                        @if ($item->variant_label)<span class="text-ink-muted">{{ $item->variant_label }}</span>@endif
                        <span class="block text-ink-muted tabular-nums">{{ $item->quantity }} × {{ money($item->unit_price) }}</span>
                    </span>
                    <span class="tabular-nums font-medium">{{ money($item->line_total) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt>{{ __('shop.cart.shipping') }}</dt><dd class="tabular-nums">{{ money($order->shipping_total) }}</dd></div>
            @if ($order->cod_fee > 0)
                <div class="flex justify-between gap-4"><dt>{{ __('shop.cart.cod_fee') }}</dt><dd class="tabular-nums">{{ money($order->cod_fee) }}</dd></div>
            @endif
            <div class="flex justify-between gap-4 border-t border-line pt-2 text-base font-semibold"><dt>{{ __('shop.cart.total') }}</dt><dd class="tabular-nums">{{ money($order->total) }}</dd></div>
        </dl>
    </section>

    <section class="card mt-6 p-4 text-sm sm:p-6">
        <h2 class="text-lg font-semibold">{{ __('shop.checkout.address') }}</h2>
        <p class="mt-2 font-medium">{{ $order->recipient_name }}</p>
        <p>{{ $order->addressSummary() }}</p>
        <p class="text-ink-muted">{{ $order->phone }}</p>
        <p class="mt-2 text-ink-muted">{{ $order->payment_method->label() }}</p>
        @if ($order->customer_note)
            <p class="mt-2 whitespace-pre-line">{{ $order->customer_note }}</p>
        @endif
    </section>

    @if ($order->customerMayCancel())
        <x-ui.confirm-form :action="route('account.orders.cancel', $order)" :message="__('shop.orders.confirm_cancel')" class="mt-6">
            <x-ui.button variant="secondary">{{ __('shop.orders.cancel') }}</x-ui.button>
        </x-ui.confirm-form>
    @endif
</x-layouts.account>
