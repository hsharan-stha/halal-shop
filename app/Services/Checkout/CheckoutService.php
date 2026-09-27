<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\Checkout\CheckoutException;
use App\Exceptions\Inventory\InventoryException;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Places an order from the server-side cart quote and allocates stock inside
 * the same transaction. Payment is never marked received because the browser
 * said so.
 */
class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private PricingService $pricing,
        private InventoryService $inventory,
    ) {}

    /**
     * @return list<PaymentMethod>
     */
    public function availableMethods(): array
    {
        $enabled = [];

        if (feature('bank_transfer_enabled')) {
            $enabled[] = PaymentMethod::BankTransfer;
        }

        if (feature('cash_on_delivery_enabled')) {
            $enabled[] = PaymentMethod::CashOnDelivery;
        }

        return $this->pricing->methodsFor($this->cart->quantities(), $enabled);
    }

    /**
     * @param  array{recipient_name: string, phone: string, postal_code: string, prefecture: string, city: string, ward?: ?string, town: string, street: string, building?: ?string, room?: ?string}  $address
     */
    public function place(User $user, array $address, PaymentMethod $method, ?string $note): Order
    {
        if (! in_array($method, $this->availableMethods(), true)) {
            throw CheckoutException::paymentUnavailable();
        }

        return DB::transaction(function () use ($user, $address, $method, $note): Order {
            $quote = $this->pricing->quote($this->cart->quantities(), $method, strict: true);

            if ($quote['lines'] === []) {
                throw CheckoutException::empty();
            }

            $order = new Order;
            $order->forceFill([
                'user_id' => $user->id,
                'order_number' => 'TMP-'.str_replace('.', '', uniqid('', true)),
                'status' => $method === PaymentMethod::BankTransfer ? OrderStatus::Pending : OrderStatus::Confirmed,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Unpaid,
                'items_total' => $quote['items_total'],
                'tax_total' => $quote['tax_total'],
                'shipping_total' => $quote['shipping_total'],
                'cod_fee' => $quote['cod_fee'],
                'total' => $quote['total'],
                'recipient_name' => $address['recipient_name'],
                'phone' => $address['phone'],
                'postal_code' => $address['postal_code'],
                'prefecture' => $address['prefecture'],
                'city' => $address['city'],
                'ward' => $address['ward'] ?? null,
                'town' => $address['town'],
                'street' => $address['street'],
                'building' => $address['building'] ?? null,
                'room' => $address['room'] ?? null,
                'customer_note' => $note,
                'placed_at' => now(),
            ])->save();

            $prefix = strtoupper((string) settings('orders.order_number_prefix', 'HS'));
            $order->forceFill([
                'order_number' => $prefix.'-'.local_today()->format('Ymd').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            ])->saveQuietly();

            foreach ($quote['lines'] as $line) {
                $variant = $line['variant'];
                try {
                    $allocations = $this->inventory->allocate($variant, $line['quantity'], $order, $user);
                } catch (InventoryException) {
                    throw CheckoutException::insufficient(0);
                }

                $order->items()->create([
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->localizedName(),
                    'variant_label' => $variant->translate('name'),
                    'sku' => $variant->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate_bps' => $line['tax_rate_bps'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'allocations' => $allocations,
                ]);
            }

            $this->rememberAddress($user, $address);
            $this->cart->clear();

            return $order;
        });
    }

    public function markPaid(Order $order, ?User $actor): void
    {
        if ($order->payment_status === PaymentStatus::Paid || $order->status === OrderStatus::Cancelled) {
            throw CheckoutException::invalidState();
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'status' => $order->status === OrderStatus::Pending ? OrderStatus::Confirmed : $order->status,
        ])->save();
    }

    public function markShipped(Order $order): void
    {
        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed], true)) {
            throw CheckoutException::invalidState();
        }

        if ($order->payment_method === PaymentMethod::BankTransfer && $order->payment_status !== PaymentStatus::Paid) {
            throw CheckoutException::invalidState();
        }

        $order->forceFill([
            'status' => OrderStatus::Shipped,
            'shipped_at' => now(),
        ])->save();
    }

    public function markCompleted(Order $order): void
    {
        if ($order->status !== OrderStatus::Shipped) {
            throw CheckoutException::invalidState();
        }

        $order->forceFill([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => $order->paid_at ?? now(),
        ])->save();
    }

    public function cancel(Order $order, ?User $actor): void
    {
        if (! $order->status->isOpen()) {
            throw CheckoutException::notCancellable();
        }

        DB::transaction(function () use ($order, $actor): void {
            $order->load('items.variant');

            foreach ($order->items as $item) {
                if ($item->variant === null) {
                    continue;
                }

                $this->inventory->release($item->variant, $item->allocations ?? [], $order, actor: $actor);
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  array{recipient_name: string, phone: string, postal_code: string, prefecture: string, city: string, ward?: ?string, town: string, street: string, building?: ?string, room?: ?string}  $address
     */
    private function rememberAddress(User $user, array $address): void
    {
        $existing = $user->addresses()->where('is_default', true)->first() ?? $user->addresses()->make(['is_default' => true]);
        $existing->fill($address);
        $existing->is_default = true;
        $user->addresses()->save($existing);
    }
}
