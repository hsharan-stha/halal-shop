<?php

namespace Database\Seeders;

use App\Enums\BatchStatus;
use App\Enums\InventoryMovementType;
use App\Enums\StorageType;
use App\Models\InventoryBatch;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Database\Seeder;

/**
 * Fictional demo stock. Everything goes through InventoryService so the
 * movement ledger matches the batch quantities, and the data deliberately
 * includes low-stock, out-of-stock, expiring, expired and on-hold examples.
 */
class InventorySeeder extends Seeder
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function run(): void
    {
        if (InventoryBatch::query()->exists()) {
            return;
        }

        $actor = User::query()->where('email', 'inventory@example.com')->first() ?? User::query()->staff()->firstOrFail();
        $variants = ProductVariant::query()->with('product:id,supplier_id,storage_type')->orderBy('id')->get();

        foreach ($variants as $index => $variant) {
            $item = $this->inventory->itemFor($variant);
            $storage = $variant->product->storage_type ?? StorageType::Ambient;
            $location = match ($storage) {
                StorageType::Frozen => '冷凍庫'.($index % 3 + 1),
                StorageType::Chilled => '冷蔵庫1',
                StorageType::Ambient => '常温棚'.chr(65 + $index % 4).'-'.($index % 5 + 1),
            };
            $item->update(['location' => $location]);

            $supplierId = $variant->product->supplier_id;
            $unitCost = (int) (round($variant->price * 0.6 / 10) * 10);

            if ($supplierId) {
                SupplierProduct::query()->create([
                    'supplier_id' => $supplierId,
                    'product_variant_id' => $variant->id,
                    'supplier_sku' => 'S-'.$variant->sku,
                    'unit_cost' => $unitCost,
                    'lead_time_days' => $storage === StorageType::Frozen ? 5 : 3,
                    'min_order_quantity' => $storage === StorageType::Frozen ? 10 : 6,
                    'is_preferred' => true,
                ]);
            }

            $shelfLife = match ($storage) {
                StorageType::Frozen => 240,
                StorageType::Chilled => 21,
                StorageType::Ambient => 300,
            };
            $receive = fn (int $quantity, int $expiresInDays, int $receivedDaysAgo, array $extra = []) => $this->inventory->receive($item, $quantity, [
                'supplier_id' => $supplierId,
                'unit_cost' => $unitCost,
                'lot_number' => 'L'.str_pad((string) (($index + 1) * 37 % 9000 + 1000), 4, '0', STR_PAD_LEFT),
                'manufactured_at' => local_today()->subDays($receivedDaysAgo + 10)->toDateString(),
                'received_at' => local_today()->subDays($receivedDaysAgo)->toDateString(),
                'expires_at' => local_today()->addDays($expiresInDays)->toDateString(),
                ...$extra,
            ], actor: $actor, reason: '初期在庫（デモデータ）');

            switch ($index % 12) {
                case 5:
                    break;
                case 3:
                    $receive(3, $shelfLife, 20);
                    break;
                case 7:
                    $receive(6, 5, 60);
                    $receive(24, $shelfLife, 5);
                    break;
                case 9:
                    $this->backdateExpiry($receive(4, 1, 90));
                    $receive(18, $shelfLife, 10);
                    break;
                case 11:
                    $receive(12, $shelfLife, 2, ['status' => BatchStatus::Quarantined]);
                    $receive(15, $shelfLife, 30);
                    break;
                default:
                    $receive(20 + ($index * 7) % 40, intdiv($shelfLife, 2), 40);
                    $receive(10 + ($index * 3) % 20, $shelfLife, 7);
            }
        }

        $this->inventory->markExpiredBatches();

        $this->damageAndTransferExamples($actor);
        $this->purchaseOrderExamples($actor);
    }

    /**
     * Expired goods cannot be received, so an expired example is received
     * with a valid date and then back-dated as if it had aged on the shelf.
     */
    private function backdateExpiry(?InventoryBatch $batch): void
    {
        $batch?->forceFill(['expires_at' => local_today()->subDays(3)])->save();
    }

    private function damageAndTransferExamples(User $actor): void
    {
        $batches = InventoryBatch::query()->where('status', BatchStatus::Available)->where('quantity', '>=', 10)->orderBy('id')->limit(2)->get();

        if ($batches->count() < 2) {
            return;
        }

        $this->inventory->dispose($batches[0], 2, InventoryMovementType::Damage, '配送中の破損（デモデータ）', $actor);
        $this->inventory->transfer($batches[1], 5, '出荷準備エリア', '出荷準備（デモデータ）', $actor);
    }

    private function purchaseOrderExamples(User $actor): void
    {
        $bySupplier = SupplierProduct::query()->orderBy('id')->get()->groupBy('supplier_id')->values();

        if ($bySupplier->isEmpty()) {
            return;
        }

        $lines = fn (int $group, int $take) => $bySupplier[$group % $bySupplier->count()]->take($take)
            ->map(fn (SupplierProduct $row) => ['product_variant_id' => $row->product_variant_id, 'quantity' => max(12, $row->min_order_quantity * 2), 'unit_cost' => $row->unit_cost])
            ->values()->all();
        $order = fn (int $group, int $take, int $expectedInDays, string $notes) => $this->purchaseOrders->save(new PurchaseOrder, [
            'supplier_id' => $bySupplier[$group % $bySupplier->count()]->first()->supplier_id,
            'expected_at' => local_today()->addDays($expectedInDays)->toDateString(),
            'notes' => $notes,
        ], $lines($group, $take), $actor);

        $order(0, 3, 10, '来月分の補充（デモデータ）');

        $ordered = $order(1, 2, 4, '定期発注（デモデータ）');
        $this->purchaseOrders->markOrdered($ordered, $actor);

        $partial = $order(2, 3, -2, '一部入荷済み（デモデータ）');
        $this->purchaseOrders->markOrdered($partial, $actor);
        $first = $partial->items()->orderBy('id')->firstOrFail();
        $this->purchaseOrders->receive($partial, [
            $first->id => ['quantity' => intdiv($first->quantity, 2), 'expires_at' => local_today()->addDays(200)->toDateString(), 'lot_number' => 'PO-DEMO-1'],
        ], $actor);
    }
}
