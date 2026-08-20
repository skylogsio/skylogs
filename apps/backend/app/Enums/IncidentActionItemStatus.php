<?php

namespace App\Enums;

enum IncidentActionItemStatus: string
{
    case Open = 'open';
    case InProgress = 'inProgress';
    case Blocked = 'blocked';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
