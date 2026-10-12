<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE packages ENGINE=InnoDB');
        }
        Schema::create('package_prices', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('billing_cycle', 20);
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('price', 12, 2);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['package_id', 'billing_cycle', 'currency']);
        });
        Schema::create('package_limits', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('limit_key', 50);
            $table->unsignedInteger('limit_value')->nullable()->comment('NULL means unlimited; absent row means not configured.');
            $table->timestamps();
            $table->unique(['package_id', 'limit_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_limits');
        Schema::dropIfExists('package_prices');
        // Keep packages on InnoDB: reverting would remove transaction safety.
    }
};
