<?php

namespace App\Enums;

enum AlertRuleAccessLevel: string
{
    case Manage = 'manage';
    case Readonly = 'readonly';
    case None = 'none';
}
