<?php

namespace App\Enums;

enum RunbookStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
