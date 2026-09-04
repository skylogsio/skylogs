<?php

namespace App\Enums;

/**
 * Timeline entry kinds. The first block is written by the application itself, the second
 * is what a responder may post; `IncidentTimelineEntryType::userWritable()` is the split.
 */
enum IncidentTimelineEntryType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Acknowledged = 'acknowledged';
    case StatusChanged = 'statusChanged';
    case Resolved = 'resolved';
    case PostMortemPublished = 'postMortemPublished';
    case Note = 'note';
    case Action = 'action';
    case Detection = 'detection';
    case Mitigation = 'mitigation';
    case Escalation = 'escalation';
    case Communication = 'communication';

    /**
     * @return list<string>
     */
    public static function userWritable(): array
    {
        return array_map(fn (self $type) => $type->value, [
            self::Note,
            self::Action,
            self::Detection,
            self::Mitigation,
            self::Escalation,
            self::Communication,
        ]);
    }
}
