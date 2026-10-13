<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('subscriptions.connection'))->table('package_payments', function (Blueprint $table): void {
            $table->string('checkout_state', 20)->default('open');
            $table->dateTime('checkout_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection(config('subscriptions.connection'))->table('package_payments', fn (Blueprint $table) => $table->dropColumn(['checkout_state', 'checkout_expires_at']));
    }
};
