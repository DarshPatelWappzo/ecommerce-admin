<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 201);
            $table->string('customer_email', 254)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('company_name')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->timestamp('order_date')->index();
            $table->string('source', 10);
            $table->string('status', 20)->default('draft')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->char('currency', 3)->default('INR');
            foreach (['subtotal', 'discount_total', 'shipping_amount', 'shipping_tax_amount', 'tax_total', 'rounding_adjustment', 'grand_total'] as $column) {
                $table->decimal($column, 15, 2)->default(0);
            }
            $table->foreignId('shipping_tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('shipping_tax_rate', 7, 4)->nullable();
            $table->string('shipping_tax_name', 100)->nullable();
            $table->string('shipping_tax_code', 50)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('draft_input')->nullable();
            $table->string('pricing_fingerprint', 64);
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name', 191);
            $table->text('variant_name')->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->unsignedInteger('quantity');
            $table->boolean('price_overridden')->default(false);
            foreach (['unit_price', 'subtotal', 'discount_amount', 'taxable_amount', 'tax_amount', 'total_amount'] as $column) {
                $table->decimal($column, 15, 2);
            }
            $table->decimal('tax_rate', 7, 4);
            $table->string('tax_name', 100);
            $table->string('tax_code', 50);
            foreach (['cgst_amount', 'sgst_amount', 'igst_amount'] as $column) {
                $table->decimal($column, 15, 2)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);
            $table->string('name', 200);
            $table->string('phone', 30);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state_name', 100);
            $table->string('state_code', 10)->nullable();
            $table->char('country_code', 2);
            $table->string('postal_code', 20);
            $table->timestamps();
            $table->unique(['order_id', 'type']);
        });
        Schema::create('order_payments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method', 20)->index();
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_account', 50)->nullable();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('status', 20)->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_account', 'gateway_payment_id'], 'order_gateway_payment_unique');
            $table->unique(['order_id', 'method', 'reference_number'], 'order_offline_receipt_unique');
        });
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('comment')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('order_shipments', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('courier_name', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 1000)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_stock_movements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('event', 20);
            $table->integer('quantity_delta');
            $table->integer('reserved_delta');
            $table->unsignedInteger('quantity_after');
            $table->unsignedInteger('reserved_after');
            $table->timestamp('created_at');
            $table->unique(['order_id', 'product_variant_id', 'event'], 'order_stock_event_unique');
        });
        Schema::create('order_idempotency_keys', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->unsignedBigInteger('principal_id');
            $table->string('operation', 80);
            $table->string('key', 128);
            $table->string('fingerprint', 64);
            $table->json('response')->nullable();
            $table->timestamps();
            $table->unique(['principal_id', 'operation', 'key'], 'order_idempotency_unique');
        });
    }

    public function down(): void
    {
        foreach (['order_idempotency_keys', 'order_stock_movements', 'order_shipments', 'order_status_histories', 'order_payments', 'order_addresses', 'order_items', 'orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
