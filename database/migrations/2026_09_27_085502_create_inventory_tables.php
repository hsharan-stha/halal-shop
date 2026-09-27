<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('country_code', 2)->default('JP')->after('address');
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku', 64)->nullable();
            $table->unsignedInteger('unit_cost')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->unsignedInteger('min_order_quantity')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['supplier_id', 'product_variant_id']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('track_batches')->default(true);
            $table->integer('quantity_on_hand')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->string('location', 60)->nullable();
            $table->timestamp('low_stock_notified_at')->nullable();
            $table->timestamps();

            $table->index('quantity_on_hand');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 30)->nullable()->unique();
            $table->string('status', 30)->default('draft');
            $table->date('expected_at')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('unit_cost');
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_variant_id']);
        });

        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 40);
            $table->string('lot_number', 60)->nullable();
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_cost')->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->date('received_at');
            $table->string('storage_type', 20)->default('ambient');
            $table->string('location', 60)->nullable();
            $table->string('status', 20)->default('available');
            $table->string('status_reason', 255)->nullable();
            $table->unsignedSmallInteger('last_alert_days')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'status', 'expires_at']);
            $table->index(['status', 'expires_at']);
            $table->index('batch_number');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity');
            $table->integer('before_quantity');
            $table->integer('after_quantity');
            $table->unsignedInteger('batch_before_quantity')->nullable();
            $table->unsignedInteger('batch_after_quantity')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('supplier_products');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'country_code']);
        });
    }
};
