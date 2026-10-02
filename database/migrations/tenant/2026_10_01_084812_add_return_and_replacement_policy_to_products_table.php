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
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_returnable')->default(false);
            $table->boolean('is_replaceable')->default(false);
            $table->unsignedInteger('return_days')->nullable();
            $table->unsignedInteger('replacement_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['is_returnable', 'is_replaceable', 'return_days', 'replacement_days']);
        });
    }
};
