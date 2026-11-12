<?php

namespace App\Enums;

enum IncidentPolicySource: string
{
    case Yaml = 'yaml';
    case Api = 'api';
}
