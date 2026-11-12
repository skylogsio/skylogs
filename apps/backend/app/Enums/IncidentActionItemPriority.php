<?php

namespace App\Enums;

enum IncidentActionItemPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
