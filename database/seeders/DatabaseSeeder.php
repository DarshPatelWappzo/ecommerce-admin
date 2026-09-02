<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'superadmin@gmail.com'], [
            'name' => 'Super Admin',
            'password' => 'Superadmin@123',
            'is_super_admin' => true,
        ]);
    }
}
