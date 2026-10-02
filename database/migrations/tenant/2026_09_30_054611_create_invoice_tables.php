<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('dispatch_approved_at')->nullable();
            $table->foreignId('dispatch_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('invoice_error')->nullable();
        });
        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->string('period', 9)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 16)->nullable()->unique();
            $table->string('period', 9)->nullable();
            $table->string('status', 10)->default('draft')->index();
            $table->string('mode', 10)->index();
            $table->date('invoice_date')->index();
            $table->timestamp('issued_at')->nullable();
            foreach (['seller', 'customer', 'billing', 'shipping', 'financials'] as $field) {
                $table->json($field);
            }
            $table->string('order_fingerprint', 64);
            $table->string('customer_name', 201)->index();
            $table->char('currency', 3);
            $table->decimal('grand_total', 15, 2);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            foreach (['created_by', 'updated_by', 'issued_by'] as $field) {
                $table->foreignId($field)->nullable()->constrained('users')->nullOnDelete();
            }
            $table->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->json('snapshot');
            $table->timestamps();
            $table->unique(['invoice_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatch_approved_by');
            $table->dropColumn(['dispatch_approved_at', 'invoice_error']);
        });
    }
};
