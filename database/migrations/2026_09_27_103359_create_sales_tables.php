<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('postal_code', 8);
            $table->string('prefecture', 20);
            $table->string('city', 80);
            $table->string('ward', 80)->nullable();
            $table->string('town', 80);
            $table->string('street', 80);
            $table->string('building', 80)->nullable();
            $table->string('room', 40)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status', 32);
            $table->string('payment_method', 32);
            $table->string('payment_status', 32);
            $table->unsignedInteger('items_total');
            $table->unsignedInteger('tax_total');
            $table->unsignedInteger('shipping_total');
            $table->unsignedInteger('cod_fee')->default(0);
            $table->unsignedInteger('total');
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('postal_code', 8);
            $table->string('prefecture', 20);
            $table->string('city', 80);
            $table->string('ward', 80)->nullable();
            $table->string('town', 80);
            $table->string('street', 80);
            $table->string('building', 80)->nullable();
            $table->string('room', 40)->nullable();
            $table->string('customer_note', 500)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku', 64);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedSmallInteger('tax_rate_bps');
            $table->unsignedInteger('tax_amount');
            $table->unsignedInteger('line_total');
            $table->json('allocations');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};
