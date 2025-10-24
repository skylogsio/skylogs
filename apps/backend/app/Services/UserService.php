<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    public function admin(): User
    {
        return cache()->tags(['user', 'admin'])->rememberForever('user:admin', function () {
            return User::where('username', 'admin')->first();
        });
    }
}
