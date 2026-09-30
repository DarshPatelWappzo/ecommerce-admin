<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->index('created_at');
        });
        Schema::create('order_payment_checkouts', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('gateway', 50);
            $table->string('gateway_account', 50);
            $table->string('gateway_order_id', 100)->nullable()->unique();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('order_payment_events', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('order_payment_checkout_id')->constrained()->restrictOnDelete();
            $table->string('event_id', 191)->unique();
            $table->string('type', 50);
            $table->string('payload_hash', 64);
            $table->string('gateway_payment_id', 100);
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_events');
        Schema::dropIfExists('order_payment_checkouts');
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['failure_code', 'failure_message', 'authorized_at', 'failed_at', 'verified_at']);
        });
    }
};
