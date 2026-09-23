<?php

namespace App\Services;

use App\Enums\Constants;
use App\Models\User;

class UserService
{
    public function admin(): User
    {
        return cache()->tags(['user', 'admin'])->rememberForever('user:admin', function () {
            return User::where('username', 'admin')->first();
        });
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->hasRole(Constants::ROLE_OWNER)) {
            return true;
        }

        return $actor->hasRole(Constants::ROLE_MANAGER) && $target->hasRole(Constants::ROLE_MEMBER);
    }
}
