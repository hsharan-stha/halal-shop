<x-layouts.shop :title="__('shop.checkout.title')" noindex>
    <div class="mx-auto max-w-3xl px-4 py-6 lg:px-6">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('shop.checkout.title') }}</h1>

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-6 grid gap-6">
            @csrf

            <section class="card space-y-4 p-4 sm:p-6">
                <h2 class="text-lg font-semibold">{{ __('shop.checkout.address') }}</h2>
                <x-ui.input name="recipient_name" :label="__('shop.checkout.recipient')" :value="$address?->recipient_name" required />
                <x-ui.input name="phone" :label="__('shop.fields.phone')" :value="$address?->phone" :hint="__('shop.hints.phone')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="postal_code" :label="__('shop.checkout.postal_code')" :value="$address?->postal_code" placeholder="123-4567" required />
                    <x-ui.select name="prefecture" :label="__('shop.checkout.prefecture')" :options="\App\Support\Prefectures::options()" :value="$address?->prefecture" :placeholder="__('shop.catalog.any')" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="city" :label="__('shop.checkout.city')" :value="$address?->city" required />
                    <x-ui.input name="ward" :label="__('shop.checkout.ward')" :value="$address?->ward" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="town" :label="__('shop.checkout.town')" :value="$address?->town" required />
                    <x-ui.input name="street" :label="__('shop.checkout.street')" :value="$address?->street" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="building" :label="__('shop.checkout.building')" :value="$address?->building" />
                    <x-ui.input name="room" :label="__('shop.checkout.room')" :value="$address?->room" />
                </div>
            </section>

            <section class="card space-y-3 p-4 sm:p-6">
                <h2 class="text-lg font-semibold">{{ __('shop.checkout.payment') }}</h2>
                @forelse ($methods as $method)
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border border-line px-3 py-2">
                        <input type="radio" name="payment_method" value="{{ $method->value }}" @checked(old('payment_method', $methods[0]->value) === $method->value) required>
                        <span>{{ $method->label() }}</span>
                    </label>
                @empty
                    <p class="text-sm text-danger">{{ __('shop.checkout.errors.payment') }}</p>
                @endforelse
                <x-ui.input name="customer_note" :label="__('shop.checkout.note')" :value="old('customer_note')" />
            </section>

            <section class="card p-4 sm:p-6">
                <h2 class="text-lg font-semibold">{{ __('shop.orders.detail') }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    @foreach ($quote['lines'] as $line)
                        <li class="flex items-start justify-between gap-3 py-3 text-sm">
                            <span class="min-w-0">{{ $line['variant']->product->localizedName() }} × {{ $line['quantity'] }}</span>
                            <span class="shrink-0 tabular-nums">{{ money($line['line_total']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>{{ __('shop.cart.shipping') }}</dt><dd class="tabular-nums">{{ money($quote['shipping_total']) }}</dd></div>
                    <div class="flex justify-between gap-4 text-base font-semibold"><dt>{{ __('shop.cart.total') }}</dt><dd class="tabular-nums">{{ money($quote['total']) }}</dd></div>
                </dl>
                <p class="mt-3 text-sm text-ink-muted">{{ __('shop.checkout.pay_later') }}</p>
                <div class="mt-4">
                    <x-ui.button :disabled="$methods === []">{{ __('shop.checkout.place') }}</x-ui.button>
                </div>
            </section>
        </form>
    </div>
</x-layouts.shop>
