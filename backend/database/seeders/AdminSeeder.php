<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => (string) config('admin.email', env('ADMIN_EMAIL', 'admin@example.com'))],
            [
                'name' => 'Super Admin',
                'password' => Hash::make((string) config('admin.password', env('ADMIN_PASSWORD', 'password'))),
            ]
        );

        $admin->assignRole($role);
    }
}
