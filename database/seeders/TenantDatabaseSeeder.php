<?php

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'orders.view',
            'orders.update',
            'inventory.view',
            'inventory.update',
        ])->mapWithKeys(fn (string $slug): array => [$slug => Permission::firstOrCreate([
            'slug' => $slug,
        ], ['name' => str($slug)->replace('.', ' ')->title()->toString()])]);

        foreach (['Admin', 'Manager', 'Inventory Manager', 'Order Manager', 'Support'] as $name) {
            $role = Role::firstOrCreate([
                'slug' => str($name)->slug()->toString(),
            ], ['name' => $name, 'status' => true]);

            if ($name === 'Admin') {
                $role->permissions()->sync($permissions->pluck('id')->all());
            }
        }
    }
}
