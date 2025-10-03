<?php

use App\Enums\Constants;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {


        $roleOwner = Role::firstOrCreate([
            'name' => Constants::ROLE_OWNER,
            'guard_name' => 'api',
        ]);

        $roleManager = Role::firstOrCreate([
            'name' => Constants::ROLE_MANAGER,
            'guard_name' => 'api',
        ]);

        $roleMember = Role::firstOrCreate([
            'name' => Constants::ROLE_MEMBER,
            'guard_name' => 'api',
        ]);

        $permissionMangerAdminUsers = Permission::createOrFirst([
            "name" => Constants::PERMISSION_MANAGE_ADMIN_USER,
            'guard_name' => 'api',
        ]);

        $permissionMangerMemberUsers = Permission::createOrFirst([
            "name" => Constants::PERMISSION_MANAGE_MEMBER_USER,
            'guard_name' => 'api',
        ]);

        $roleOwner->givePermissionTo($permissionMangerMemberUsers);
        $roleOwner->givePermissionTo($permissionMangerAdminUsers);

        $roleManager->givePermissionTo($permissionMangerMemberUsers);



    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleOwner = Role::firstOrCreate([
            'name' => Constants::ROLE_OWNER,
        ])->delete();

        $roleManager = Role::firstOrCreate([
            'name' => Constants::ROLE_MANAGER,
        ])->delete();

        $roleMember = Role::firstOrCreate([
            'name' => Constants::ROLE_MEMBER,
        ])->delete();

        $permissionMangerAdminUsers = Permission::createOrFirst([
            "name" => Constants::PERMISSION_MANAGE_ADMIN_USER
        ])->delete();

        $permissionMangerMemberUsers = Permission::createOrFirst([
            "name" => Constants::PERMISSION_MANAGE_MEMBER_USER
        ])->delete();

    }
};
