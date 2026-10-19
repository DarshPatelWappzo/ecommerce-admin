<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('subscriptions.connection'));
        foreach (['packages', 'user_packages'] as $tableName) {
            $schema->table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('grace_period_days')->default(7);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('subscriptions.connection'));
        foreach (['user_packages', 'packages'] as $tableName) {
            $schema->table($tableName, fn (Blueprint $table) => $table->dropColumn('grace_period_days'));
        }
    }
};
