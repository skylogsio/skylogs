<?php

namespace App\Enums;

enum PostMortemStatus: string
{
    case Draft = 'draft';
    case InReview = 'inReview';
    case Approved = 'approved';
    case Published = 'published';
}
