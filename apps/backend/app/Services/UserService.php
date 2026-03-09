<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class UserService
{
    public function admin(): User
    {
        return cache()->tags(['user', 'admin'])->rememberForever('user:admin', function () {
            return User::where('username', 'admin')->first();
        });
    }

}
