<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection();
        $db->table('permissions')->insertOrIgnore(['slug' => 'audit_logs.view', 'name' => 'View Audit Logs', 'created_at' => now(), 'updated_at' => now()]);
        $permission = $db->table('permissions')->where('slug', 'audit_logs.view')->value('id');
        foreach ($db->table('roles')->where('slug', 'admin')->pluck('id') as $role) {
            $db->table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
        }
    }

    public function down(): void
    {
        $db = DB::connection();
        $ids = $db->table('permissions')->where('slug', 'audit_logs.view')->pluck('id');
        $db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
        $db->table('permissions')->whereIn('id', $ids)->delete();
    }
};
