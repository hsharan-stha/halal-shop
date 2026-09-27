<?php

namespace App\Http\Requests\Admin;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.manage') && $this->user()->can('inventory.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = local_today()->toDateString();

        return [
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0', 'max:'.PurchaseOrderRequest::MAX_QUANTITY],
            'lines.*.batch_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/'],
            'lines.*.lot_number' => ['nullable', 'string', 'max:60'],
            'lines.*.expires_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'lines.*.manufactured_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'lines.*.location' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Line ids must belong to this order, quantities may not exceed what is
     * still outstanding, and batch-tracked items need an expiry date.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var PurchaseOrder $order */
            $order = $this->route('purchase_order');
            $lines = $order->items()->with('variant.inventoryItem')->get()->keyBy('id');
            $total = 0;

            foreach ((array) $this->input('lines') as $lineId => $receipt) {
                $quantity = (int) ($receipt['quantity'] ?? 0);
                /** @var PurchaseOrderItem|null $line */
                $line = $lines->get((int) $lineId);

                if (! $line) {
                    $validator->errors()->add('lines', __('admin.purchase_orders.invalid_line'));

                    continue;
                }

                if ($quantity > $line->remainingQuantity()) {
                    $validator->errors()->add("lines.{$lineId}.quantity", __('admin.purchase_orders.over_receipt', ['remaining' => $line->remainingQuantity()]));
                }

                if ($quantity > 0 && ($line->variant?->inventoryItem?->track_batches ?? true) && blank($receipt['expires_at'] ?? null)) {
                    $validator->errors()->add("lines.{$lineId}.expires_at", __('admin.purchase_orders.expiry_required'));
                }

                $total += $quantity;
            }

            if ($total === 0) {
                $validator->errors()->add('lines', __('admin.purchase_orders.nothing_to_receive'));
            }
        }];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function receipts(): array
    {
        $receipts = [];

        foreach ($this->validated('lines') as $lineId => $receipt) {
            if ((int) ($receipt['quantity'] ?? 0) > 0) {
                $receipts[(int) $lineId] = [...$receipt, 'quantity' => (int) $receipt['quantity']];
            }
        }

        return $receipts;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'lines.*.quantity' => __('admin.purchase_orders.receive_quantity'),
            'lines.*.batch_number' => __('admin.batches.batch_number'),
            'lines.*.lot_number' => __('admin.batches.lot_number'),
            'lines.*.expires_at' => __('admin.batches.expires_at'),
            'lines.*.manufactured_at' => __('admin.batches.manufactured_at'),
            'lines.*.location' => __('admin.batches.location'),
        ];
    }
}
