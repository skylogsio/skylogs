<?php

namespace App\Enums;

enum EndpointType: string
{
    case FLOW = 'flow';
    case TELEGRAM = 'telegram';
    case SMS = 'sms';
    case CALL = 'call';
    case TEAMS = 'teams';
    case EMAIL = 'email';
    case MATTER_MOST = 'matter-most';

    public static function GetTypes()
    {
        return [
            self::TELEGRAM,
            self::SMS,
            self::CALL,
            self::TEAMS,
            self::EMAIL,
            self::MATTER_MOST,
        ];
    }
}
