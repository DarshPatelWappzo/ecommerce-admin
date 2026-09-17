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
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $slug = 'categories.'.$action;
            DB::table('permissions')->insertOrIgnore([
                'name' => 'Categories '.ucfirst($action),
                'slug' => $slug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');

            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('slug', [
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        ])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
