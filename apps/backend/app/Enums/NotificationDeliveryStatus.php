<?php

namespace App\Enums;

enum NotificationDeliveryStatus: string
{
    case PENDING = 'pending';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
}
