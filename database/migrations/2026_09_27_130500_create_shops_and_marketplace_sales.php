<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('postal_code', 8)->nullable();
            $table->string('prefecture', 20);
            $table->string('city', 80);
            $table->string('town', 80);
            $table->string('street', 80);
            $table->string('building', 80)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('is_staff')->constrained('shops')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('id')->constrained('shops')->restrictOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->after('user_id')->constrained('shops')->restrictOnDelete();
            $table->foreignId('pickup_shop_id')->nullable()->after('shop_id')->constrained('shops')->nullOnDelete();
            $table->string('delivery_to', 20)->default('customer')->after('pickup_shop_id');
            $table->unsignedInteger('commission_amount')->default(0)->after('total');
            $table->index(['shop_id', 'placed_at']);
        });

        if (DB::table('products')->whereNull('shop_id')->exists()) {
            $now = now();
            $shopId = DB::table('shops')->insertGetId([
                'ulid' => (string) Str::ulid(),
                'name' => 'メイン店舗',
                'slug' => 'main-shop',
                'prefecture' => '東京都',
                'city' => '千代田区',
                'town' => '丸の内',
                'street' => '1-1-1',
                'latitude' => 35.6812360,
                'longitude' => 139.7671250,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('products')->whereNull('shop_id')->update(['shop_id' => $shopId]);

            $orders = DB::table('orders')->whereNull('shop_id')->get(['id', 'items_total']);

            foreach ($orders as $order) {
                $productShopId = DB::table('order_items')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->where('order_items.order_id', $order->id)
                    ->value('products.shop_id');

                DB::table('orders')->where('id', $order->id)->update([
                    'shop_id' => $productShopId ?: $shopId,
                    'delivery_to' => 'customer',
                    'commission_amount' => (int) floor(((int) $order->items_total) * 10 / 100),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'placed_at']);
            $table->dropConstrainedForeignId('pickup_shop_id');
            $table->dropConstrainedForeignId('shop_id');
            $table->dropColumn(['delivery_to', 'commission_amount']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });

        Schema::dropIfExists('shops');
    }
};
