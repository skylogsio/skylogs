<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;

final class DataSourceCredentials
{
    public static function username(DataSource $dataSource): ?string
    {
        $username = $dataSource->username;
        $password = $dataSource->password;

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            return null;
        }

        return $username;
    }

    public static function password(DataSource $dataSource): ?string
    {
        return self::username($dataSource) === null ? null : (string) $dataSource->password;
    }

    public static function token(DataSource $dataSource): ?string
    {
        $token = $dataSource->apiToken ?: $dataSource->api_token;

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }
}
