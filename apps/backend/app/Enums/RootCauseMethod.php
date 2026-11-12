<?php

namespace App\Enums;

enum RootCauseMethod: string
{
    case FiveWhys = 'fiveWhys';
    case Fishbone = 'fishbone';
    case Timeline = 'timeline';
    case Other = 'other';
}
