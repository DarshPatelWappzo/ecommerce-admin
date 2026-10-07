<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('auditable_type')->nullable()->change();
            $table->unsignedBigInteger('auditable_id')->nullable()->change();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->index('user_id');
            $table->index('auditable_id');
            $table->index('created_at');
        });
    }

    /** Keep optional record references nullable on rollback to preserve newly recorded history. */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['auditable_id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn(['metadata', 'ip_address', 'user_agent']);
        });
    }
};
