<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public const MAX_UNIT_COST = 10_000_000;

    public const MAX_QUANTITY = 100_000;

    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.manage');
    }

    protected function prepareForValidation(): void
    {
        $lines = collect((array) $this->input('lines', []))
            ->filter(fn ($line) => is_array($line) && filled($line['product_variant_id'] ?? null))
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'expected_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.product_variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
            'lines.*.unit_cost' => ['required', 'integer', 'min:0', 'max:'.self::MAX_UNIT_COST],
        ];
    }

    /**
     * Every line must come from the same halal shop, and that shop owns the
     * order. A shop only sees its own variants, so this also stops it from
     * ordering stock for another shop.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $order = $this->route('purchase_order');
            $shopId = $this->shopId();

            if ($shopId === null || ($order instanceof PurchaseOrder && $order->shop_id !== null && (int) $order->shop_id !== $shopId)) {
                $validator->errors()->add('lines', __('admin.purchase_orders.single_shop'));
            }
        }];
    }

    /**
     * The shop that owns every line, or null when the lines are mixed.
     */
    public function shopId(): ?int
    {
        $shopIds = ProductVariant::query()
            ->whereKey(array_column($this->lines(), 'product_variant_id'))
            ->with('product:id,shop_id')
            ->get()
            ->map(fn (ProductVariant $variant) => $variant->product?->shop_id)
            ->unique();

        return $shopIds->count() === 1 && $shopIds->first() !== null ? (int) $shopIds->first() : null;
    }

    /**
     * @return array{supplier_id: int, expected_at: ?string, notes: ?string}
     */
    public function attributesForModel(): array
    {
        return [
            'supplier_id' => (int) $this->validated('supplier_id'),
            'expected_at' => $this->validated('expected_at'),
            'notes' => $this->validated('notes'),
        ];
    }

    /**
     * @return list<array{product_variant_id: int, quantity: int, unit_cost: int}>
     */
    public function lines(): array
    {
        return array_map(fn (array $line) => [
            'product_variant_id' => (int) $line['product_variant_id'],
            'quantity' => (int) $line['quantity'],
            'unit_cost' => (int) $line['unit_cost'],
        ], $this->validated('lines'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => __('admin.purchase_orders.supplier'),
            'expected_at' => __('admin.purchase_orders.expected_at'),
            'lines' => __('admin.purchase_orders.lines'),
            'lines.*.product_variant_id' => __('admin.purchase_orders.product'),
            'lines.*.quantity' => __('admin.purchase_orders.quantity'),
            'lines.*.unit_cost' => __('admin.purchase_orders.unit_cost'),
        ];
    }
}
