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

    public function getUserByMainId($mainToken): ?User
    {

        $mainId = $this->getUserIdFromToken($mainToken);

        if (empty($mainId)) return null;

        return cache()->tags(['user', $mainId])->remember('user:admin',3600, function ()use ($mainId) {
            return User::where('mainClusterId', $mainId)->first();
        });
    }

    public function getUserIdFromToken($jwtToken)
    {
        $decoded = JWT::decode(
            $jwtToken,
            new Key(config('jwt.secret'), 'HS256')
        );

        return $decoded->sub ?? null;
    }
}
