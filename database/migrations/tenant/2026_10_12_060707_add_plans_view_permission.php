<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore(['slug' => 'plans.view', 'name' => 'Plans View', 'created_at' => now(), 'updated_at' => now()]);
        $permission = DB::table('permissions')->where('slug', 'plans.view')->value('id');
        foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('slug', 'plans.view')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
