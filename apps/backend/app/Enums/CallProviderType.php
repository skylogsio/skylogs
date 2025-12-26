<?php

namespace App\Enums;

enum CallProviderType: string
{
    case KAVE_NEGAR = 'kave_negar';

    public static function GetList()
    {
        return collect([
            self::KAVE_NEGAR->value,
        ]);
    }
}
