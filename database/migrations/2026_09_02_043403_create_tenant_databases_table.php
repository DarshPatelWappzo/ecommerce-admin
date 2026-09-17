<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_databases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->unique()->constrained('user_domains')->cascadeOnDelete();
            $table->string('database_name')->unique();
            $table->string('status', 30)->default('creating')->index();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('provisioning_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_databases');
    }
};
