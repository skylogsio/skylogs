<?php

namespace Database\Seeders;

use App\Enums\Constants;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'admin',
                'password' => Hash::make('123456'),
            ]
        );

        $roleOwner = Role::firstOrCreate([
            'name' => Constants::ROLE_OWNER->value,
            'guard_name' => 'api',
        ]);


        if (!$user->hasRole($roleOwner))
            $user->assignRole($roleOwner);


        $roleManager = Role::firstOrCreate([
            'name' => Constants::ROLE_MANAGER->value,
            'guard_name' => 'api',
        ]);

        $roleMember = Role::firstOrCreate([
            'name' => Constants::ROLE_MEMBER->value,
            'guard_name' => 'api',
        ]);

        $permissionMangerAdminUsers = Permission::firstOrCreate([
            'name' => Constants::PERMISSION_MANAGE_ADMIN_USER->value,
            'guard_name' => 'api',
        ]);

        $permissionMangerMemberUsers = Permission::firstOrCreate([
            'name' => Constants::PERMISSION_MANAGE_MEMBER_USER->value,
            'guard_name' => 'api',
        ]);

        if (!$roleOwner->hasPermissionTo($permissionMangerMemberUsers))
            $roleOwner->givePermissionTo($permissionMangerMemberUsers);

        if (!$roleOwner->hasPermissionTo($permissionMangerAdminUsers))
            $roleOwner->givePermissionTo($permissionMangerAdminUsers);

        if (!$roleManager->hasPermissionTo($permissionMangerMemberUsers))
            $roleManager->givePermissionTo($permissionMangerMemberUsers);
    }
}
