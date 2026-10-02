<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS = ['create', 'confirm', 'process', 'cancel', 'payments', 'ship', 'deliver', 'price_override', 'discount'];

    public function up(): void
    {
        foreach (['view', 'update', ...self::ACTIONS] as $action) {
            $slug = 'orders.'.$action;
            DB::table('permissions')->insertOrIgnore(['slug' => $slug, 'name' => 'Orders '.ucfirst(str_replace('_', ' ', $action)), 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->where('slug', 'admin')->pluck('id') as $role) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', array_map(fn (string $action): string => 'orders.'.$action, self::ACTIONS))->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
