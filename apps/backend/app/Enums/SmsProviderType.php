<?php

namespace App\Enums;

enum SmsProviderType: string
{
    case KAVE_NEGAR = 'kaveNegar';

    public static function GetList()
    {
        return collect([
            self::KAVE_NEGAR->value => 'Kave Negar',
        ]);
    }

    public static function GetKeys()
    {
        return collect([
            self::KAVE_NEGAR->value,
        ]);
    }
}
