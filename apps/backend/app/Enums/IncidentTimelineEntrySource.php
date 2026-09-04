<?php

namespace App\Enums;

enum IncidentTimelineEntrySource: string
{
    case System = 'system';
    case User = 'user';
    case Alert = 'alert';
    case Webhook = 'webhook';
}
