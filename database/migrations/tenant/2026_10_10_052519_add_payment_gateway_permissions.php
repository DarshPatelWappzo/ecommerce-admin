<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['payment_gateways.view', 'payment_gateways.update'] as $slug) {
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => str($slug)->replace(['_', '.'], ' ')->title()->toString(), 'created_at' => now(), 'updated_at' => now()]);
            $permission = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['payment_gateways.view', 'payment_gateways.update'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
