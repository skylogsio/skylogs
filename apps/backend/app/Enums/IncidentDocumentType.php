<?php

namespace App\Enums;

enum IncidentDocumentType: string
{
    case Screenshot = 'screenshot';
    case Log = 'log';
    case Metric = 'metric';
    case Diagram = 'diagram';
    case Report = 'report';
    case Other = 'other';
}
