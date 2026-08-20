<?php

namespace App\Enums;

enum IncidentActionItemCategory: string
{
    case Prevention = 'prevention';
    case Detection = 'detection';
    case Mitigation = 'mitigation';
    case Process = 'process';
    case Documentation = 'documentation';
    case Other = 'other';
}
