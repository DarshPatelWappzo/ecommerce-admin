<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'update', 'delete', 'addresses'] as $action) {
            $slug = 'customers.' . $action;
            DB::table('permissions')->insertOrIgnore(['name' => 'Customers ' . ucfirst($action), 'slug' => $slug, 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.addresses'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
