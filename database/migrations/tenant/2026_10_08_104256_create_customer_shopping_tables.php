<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_customer_id')->nullable()->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('coupon_code', 50)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id']);
        });
        Schema::create('wishlists', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'product_id']);
        });
        Schema::create('cart_orders', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('cart_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->json('items_snapshot');
            $table->string('payment_method', 20);
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index(['cart_id', 'converted_at']);
        });
        Schema::table('orders', fn(Blueprint $table) => $table->index(['customer_id', 'order_date', 'id'], 'orders_customer_history_index'));
    }

    public function down(): void
    {
        Schema::table('orders', fn(Blueprint $table) => $table->dropIndex('orders_customer_history_index'));
        foreach (['cart_orders', 'wishlists', 'cart_items', 'carts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
