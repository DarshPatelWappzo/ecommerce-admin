<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->boolean('is_tenant_visible')->default(true);
            $table->boolean('public_infrastructure')->default(false);
        });
        Schema::table('user_packages', function (Blueprint $table): void {
            $table->string('billing_cycle', 20)->nullable();
            $table->dateTime('grace_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_packages', fn (Blueprint $table) => $table->dropColumn(['billing_cycle', 'grace_expires_at']));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn(['is_tenant_visible', 'public_infrastructure']));
    }
};
