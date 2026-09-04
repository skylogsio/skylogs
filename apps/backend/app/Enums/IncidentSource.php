<?php

namespace App\Enums;

enum IncidentSource: string
{
    case Manual = 'manual';
    case Policy = 'policy';
}
