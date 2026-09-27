<?php

namespace App\Http\Requests\Admin;

use App\Enums\BatchStatus;
use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.receive');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var InventoryItem $item */
        $item = $this->route('item');
        $tracked = $item->track_batches;

        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.PurchaseOrderRequest::MAX_QUANTITY],
            'batch_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/'],
            'lot_number' => ['nullable', 'string', 'max:60'],
            'expires_at' => [$tracked ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.local_today()->toDateString()],
            'manufactured_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.local_today()->toDateString()],
            'received_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.local_today()->toDateString()],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:'.PurchaseOrderRequest::MAX_UNIT_COST],
            'location' => ['nullable', 'string', 'max:60'],
            'quarantine' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function batchAttributes(): array
    {
        return [
            'batch_number' => $this->validated('batch_number'),
            'lot_number' => $this->validated('lot_number'),
            'expires_at' => $this->validated('expires_at'),
            'manufactured_at' => $this->validated('manufactured_at'),
            'received_at' => $this->validated('received_at'),
            'supplier_id' => $this->validated('supplier_id'),
            'unit_cost' => $this->validated('unit_cost'),
            'location' => $this->validated('location'),
            'status' => $this->boolean('quarantine') ? BatchStatus::Quarantined : BatchStatus::Available,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => __('admin.inventory.quantity'),
            'batch_number' => __('admin.batches.batch_number'),
            'lot_number' => __('admin.batches.lot_number'),
            'expires_at' => __('admin.batches.expires_at'),
            'manufactured_at' => __('admin.batches.manufactured_at'),
            'received_at' => __('admin.batches.received_at'),
            'unit_cost' => __('admin.batches.unit_cost'),
            'location' => __('admin.batches.location'),
        ];
    }
}
