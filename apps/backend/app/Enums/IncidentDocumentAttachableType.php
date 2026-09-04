<?php

namespace App\Enums;

enum IncidentDocumentAttachableType: string
{
    case Incident = 'incident';
    case PostMortem = 'postMortem';
}
