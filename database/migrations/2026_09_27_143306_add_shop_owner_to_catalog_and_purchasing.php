<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A halal shop manages its own catalogue and purchasing. A null shop_id keeps
 * the row platform-wide: every shop may use it, only a super admin may edit it.
 * Purchase orders are private, so existing ones are moved to the shop that
 * ordered the goods.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['categories', 'brands', 'halal_certifications', 'suppliers', 'purchase_orders'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->restrictOnDelete();
            });
        }

        $orders = DB::table('purchase_orders')->whereNull('shop_id')->pluck('id');

        foreach ($orders as $orderId) {
            $shopId = DB::table('purchase_order_items')
                ->join('product_variants', 'product_variants.id', '=', 'purchase_order_items.product_variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('purchase_order_items.purchase_order_id', $orderId)
                ->value('products.shop_id');

            if ($shopId) {
                DB::table('purchase_orders')->where('id', $orderId)->update(['shop_id' => $shopId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('shop_id');
            });
        }
    }
};
