<?php

namespace App\Enums;

enum AlertRuleBehaviorRuleType: string
{
    case NOTIFICATION = 'notification';

    case SILENT = 'silent';

    case TEMPLATE = 'template';
}
