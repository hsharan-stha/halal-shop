<?php

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;
use App\Enums\StorageType;
use App\Exceptions\Checkout\CheckoutException;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Server-side cart totals. Prices, tax, shipping and fees are never taken
 * from the browser.
 */
class PricingService
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     * @return array{lines: list<array<string, mixed>>, items_total: int, tax_total: int, shipping_total: int, cod_fee: int, total: int, unavailable: list<int>}
     */
    public function quote(array $quantities, ?PaymentMethod $method = null, bool $strict = false): array
    {
        $variants = $this->variants(array_keys($quantities));
        $lines = [];
        $unavailable = [];

        foreach ($quantities as $variantId => $quantity) {
            $variant = $variants->get((int) $variantId);

            if (! $variant instanceof ProductVariant) {
                $unavailable[] = (int) $variantId;

                if ($strict) {
                    throw CheckoutException::unavailable();
                }

                continue;
            }

            $sellable = $variant->inventoryItem?->sellableQuantity() ?? 0;
            $quantity = (int) $quantity;

            if ($quantity < 1 || $sellable < $quantity) {
                $unavailable[] = $variant->id;

                if ($strict) {
                    throw CheckoutException::insufficient($sellable);
                }

                continue;
            }

            $rateBps = (int) ($variant->product->taxClass?->rateOn()?->rate_bps ?? 0);
            $gross = $variant->price * $quantity;
            $included = (bool) settings('tax.prices_include_tax', true);
            $tax = $included
                ? $this->round($gross * $rateBps / (10000 + $rateBps))
                : $this->round($gross * $rateBps / 10000);

            $lines[] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => $variant->price,
                'tax_rate_bps' => $rateBps,
                'tax_amount' => $tax,
                'line_total' => $included ? $gross : $gross + $tax,
            ];
        }

        $itemsTotal = (int) collect($lines)->sum('line_total');
        $taxTotal = (int) collect($lines)->sum('tax_amount');
        $shipping = $this->shipping($lines, $itemsTotal);
        $codFee = $method === PaymentMethod::CashOnDelivery ? $this->codFee($itemsTotal + $shipping) : 0;

        return [
            'lines' => $lines,
            'items_total' => $itemsTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shipping,
            'cod_fee' => $codFee,
            'total' => $itemsTotal + $shipping + $codFee,
            'unavailable' => $unavailable,
        ];
    }

    /**
     * @param  list<PaymentMethod>  $enabled
     * @return list<PaymentMethod>
     */
    public function methodsFor(array $quantities, array $enabled): array
    {
        $quote = $this->quote($quantities);
        $merchandise = $quote['items_total'] + $quote['shipping_total'];
        $max = (int) settings('payment.cod_max_amount', 300000);

        return array_values(array_filter($enabled, function (PaymentMethod $method) use ($merchandise, $max): bool {
            if ($method !== PaymentMethod::CashOnDelivery) {
                return true;
            }

            return $max === 0 || $merchandise <= $max;
        }));
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, ProductVariant>
     */
    private function variants(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return ProductVariant::query()
            ->with([
                'product.taxClass.rates',
                'inventoryItem' => fn ($query) => $query->withSellableQuantity(),
            ])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->published())
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function shipping(array $lines, int $itemsTotal): int
    {
        if ($lines === []) {
            return 0;
        }

        $threshold = (int) settings('shipping.free_shipping_threshold', 0);

        if ($threshold > 0 && $itemsTotal >= $threshold) {
            return 0;
        }

        $fee = (int) settings('shipping.flat_rate', 660);
        $frozen = collect($lines)->contains(fn (array $line) => $line['variant']->product->storage_type === StorageType::Frozen);

        if ($frozen && feature('frozen_shipping_enabled')) {
            $fee += (int) settings('shipping.frozen_surcharge', 440);
        }

        return max(0, $fee);
    }

    private function codFee(int $beforeFee): int
    {
        $max = (int) settings('payment.cod_max_amount', 300000);

        if ($max > 0 && $beforeFee > $max) {
            throw CheckoutException::paymentUnavailable();
        }

        return (int) settings('payment.cod_fee', 330);
    }

    private function round(float $amount): int
    {
        return match (settings('tax.rounding', 'floor')) {
            'ceil' => (int) ceil($amount),
            'round' => (int) round($amount),
            default => (int) floor($amount),
        };
    }
}
