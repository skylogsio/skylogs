<?php

namespace App\Enums;

enum HealthAlertType: string
{
    case DATASOURCE = 'datasource';
    case HTTP = 'http';

    /**
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [
            self::DATASOURCE,
            self::HTTP,
        ];
    }
}
