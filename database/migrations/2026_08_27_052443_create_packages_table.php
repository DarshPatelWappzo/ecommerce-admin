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
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();

            $table->unsignedBigInteger('min_monthly_users')->default(0);
            $table->unsignedBigInteger('max_monthly_users')->nullable();

            $table->decimal('cpu_vcores', 5, 2)->nullable();
            $table->decimal('ram_gb', 8, 2)->nullable();
            $table->unsignedSmallInteger('application_servers')->default(1);
            $table->string('database_type', 100)->nullable();
            $table->string('infrastructure_summary');

            $table->decimal('min_monthly_cost', 12, 2);
            $table->decimal('max_monthly_cost', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');

            $table->decimal('bandwidth_gb', 10, 2)->nullable();
            $table->decimal('storage_gb', 10, 2)->nullable();

            $table->boolean('backup_included')->default(false);
            $table->boolean('cdn_included')->default(false);
            $table->boolean('load_balancer_included')->default(false);

            $table->text('description')->nullable();
            $table->text('cost_disclaimer')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_recommended')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
            $table->index(['min_monthly_users', 'max_monthly_users']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
