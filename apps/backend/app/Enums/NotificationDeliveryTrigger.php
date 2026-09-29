<?php

namespace App\Enums;

enum NotificationDeliveryTrigger: string
{
    case AUTO = 'auto';
    case MANUAL = 'manual';
}
