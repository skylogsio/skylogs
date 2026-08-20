<?php

namespace Tests\Support;

use App\Enums\IncidentActionItemCategory;
use App\Enums\IncidentActionItemPriority;
use App\Enums\IncidentActionItemStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use App\Enums\PostMortemStatus;
use App\Models\Incident;
use App\Models\IncidentActionItem;
use App\Models\IncidentDocument;
use App\Models\IncidentTimelineEntry;
use App\Models\PostMortem;
use Carbon\Carbon;

class IncidentTestData
{
    /**
     * @param  list<string>  $teamIds
     * @param  array<string, mixed>  $overrides
     */
    public static function createIncident(string $createdBy, array $teamIds, array $overrides = []): Incident
    {
        $now = Carbon::now();

        return Incident::create([
            'title' => $overrides['title'] ?? 'Test incident '.uniqid(),
            'description' => $overrides['description'] ?? 'test description',
            'teamIds' => $teamIds,
            'tags' => $overrides['tags'] ?? [],
            'startedAt' => $overrides['startedAt'] ?? $now,
            'detectedAt' => $overrides['detectedAt'] ?? $now,
            'resolvedAt' => $overrides['resolvedAt'] ?? null,
            'resolvedBy' => $overrides['resolvedBy'] ?? null,
            'alertRuleIds' => $overrides['alertRuleIds'] ?? [],
            'severity' => $overrides['severity'] ?? IncidentSeverity::Sev3,
            'status' => $overrides['status'] ?? IncidentStatus::Open,
            'source' => $overrides['source'] ?? IncidentSource::Manual,
            'createdBy' => $createdBy,
            'acknowledgements' => $overrides['acknowledgements'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function createPostMortem(Incident $incident, string $authorId, array $overrides = []): PostMortem
    {
        return PostMortem::create([
            'incidentId' => (string) $incident->id,
            'status' => $overrides['status'] ?? PostMortemStatus::Draft,
            'summary' => $overrides['summary'] ?? 'Test postmortem summary',
            'impact' => $overrides['impact'] ?? null,
            'detection' => $overrides['detection'] ?? null,
            'resolution' => $overrides['resolution'] ?? null,
            'rootCause' => $overrides['rootCause'] ?? [
                'method' => null,
                'whys' => [],
                'contributingFactors' => [],
                'statement' => null,
            ],
            'whatWentWell' => $overrides['whatWentWell'] ?? [],
            'whatWentWrong' => $overrides['whatWentWrong'] ?? [],
            'lessonsLearned' => $overrides['lessonsLearned'] ?? [],
            'authorId' => $authorId,
            'reviewerIds' => $overrides['reviewerIds'] ?? [],
            'dueAt' => $overrides['dueAt'] ?? null,
            'publishedAt' => $overrides['publishedAt'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function createTimelineEntry(Incident $incident, array $overrides = []): IncidentTimelineEntry
    {
        return IncidentTimelineEntry::create([
            'incidentId' => (string) $incident->id,
            'type' => $overrides['type'] ?? IncidentTimelineEntryType::Note,
            'source' => $overrides['source'] ?? IncidentTimelineEntrySource::User,
            'occurredAt' => $overrides['occurredAt'] ?? Carbon::now(),
            'userId' => $overrides['userId'] ?? null,
            'message' => $overrides['message'] ?? 'Test timeline entry',
            'meta' => $overrides['meta'] ?? [],
            'isPublic' => $overrides['isPublic'] ?? false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function createActionItem(Incident $incident, array $overrides = []): IncidentActionItem
    {
        return IncidentActionItem::create([
            'incidentId' => (string) $incident->id,
            'postMortemId' => $overrides['postMortemId'] ?? null,
            'title' => $overrides['title'] ?? 'Test action item '.uniqid(),
            'description' => $overrides['description'] ?? '',
            'ownerId' => $overrides['ownerId'] ?? null,
            'teamId' => $overrides['teamId'] ?? null,
            'priority' => $overrides['priority'] ?? IncidentActionItemPriority::Medium,
            'category' => $overrides['category'] ?? IncidentActionItemCategory::Other,
            'status' => $overrides['status'] ?? IncidentActionItemStatus::Open,
            'dueAt' => $overrides['dueAt'] ?? null,
            'completedAt' => $overrides['completedAt'] ?? null,
            'createdBy' => $overrides['createdBy'] ?? null,
        ]);
    }

    /**
     * Removes the incident and everything documented against it, so a test only has to
     * track the incidents it created.
     */
    public static function deleteIncident(Incident $incident): void
    {
        $incidentId = (string) $incident->id;

        PostMortem::query()->where('incidentId', $incidentId)->delete();
        IncidentTimelineEntry::query()->where('incidentId', $incidentId)->delete();
        IncidentDocument::query()->where('incidentId', $incidentId)->delete();
        IncidentActionItem::query()->where('incidentId', $incidentId)->delete();
        Incident::query()->where('_id', $incidentId)->delete();
    }
}
