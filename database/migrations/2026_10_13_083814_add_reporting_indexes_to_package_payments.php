<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('subscriptions.connection'))->table('package_payments', function (Blueprint $table): void {
            $table->index(['mode', 'status', 'paid_at'], 'platform_payments_capture_report');
            $table->index(['mode', 'created_at'], 'platform_payments_attempt_report');
        });
    }

    public function down(): void
    {
        Schema::connection(config('subscriptions.connection'))->table('package_payments', function (Blueprint $table): void {
            $table->dropIndex('platform_payments_capture_report');
            $table->dropIndex('platform_payments_attempt_report');
        });
    }
};
