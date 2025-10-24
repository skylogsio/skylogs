<?php

namespace App\Enums;

enum ClusterType: string
{
    case MAIN = 'main';
    case AGENT = 'agent';

}
