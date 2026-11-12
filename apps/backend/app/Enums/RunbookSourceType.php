<?php

namespace App\Enums;

enum RunbookSourceType: string
{
    case Steps = 'steps';
    case Markdown = 'markdown';
    case ExternalUrl = 'externalUrl';
}
