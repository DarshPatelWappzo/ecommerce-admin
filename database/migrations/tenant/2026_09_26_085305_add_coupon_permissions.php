<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $slug = 'coupons.' . $action;
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => 'Coupons ' . ucfirst($action), 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
