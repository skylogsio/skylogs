<?php

namespace App\Services;

use App\Enums\IncidentTimelineEntrySource;
use App\Enums\IncidentTimelineEntryType;
use App\Models\Incident;
use App\Models\IncidentTimelineEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Writes and reads the incident timeline.
 *
 * Deliberately unaware of who may see an incident: callers authorise through
 * `IncidentService` first, which keeps this service usable from `IncidentService` itself
 * without the two depending on each other.
 */
class IncidentTimelineService
{
    /**
     * A fact the application observed. Attributed to the acting user when there is one,
     * but always marked as coming from the system so the UI can style it apart from notes.
     *
     * @param  array<string, mixed>  $meta
     */
    public function recordSystemEntry(
        Incident $incident,
        IncidentTimelineEntryType $type,
        string $message,
        ?User $user = null,
        array $meta = [],
    ): IncidentTimelineEntry {
        return IncidentTimelineEntry::create([
            'incidentId' => (string) $incident->id,
            'type' => $type,
            'occurredAt' => now(),
            'userId' => $user?->id,
            'source' => IncidentTimelineEntrySource::System,
            'message' => $message,
            'meta' => $meta,
            'isPublic' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function recordUserEntry(User $user, Incident $incident, array $validated): IncidentTimelineEntry
    {
        $entry = IncidentTimelineEntry::create([
            'incidentId' => (string) $incident->id,
            'type' => $validated['type'],
            'occurredAt' => $this->parseDate($validated['occurredAt'] ?? null) ?? now(),
            'userId' => $user->id,
            'source' => IncidentTimelineEntrySource::User,
            'message' => $validated['message'],
            'meta' => $validated['meta'] ?? [],
            'isPublic' => (bool) ($validated['isPublic'] ?? false),
        ]);

        $entry->load('user');

        return $entry;
    }

    /**
     * @return Builder<IncidentTimelineEntry>
     */
    public function query(Incident $incident): Builder
    {
        return IncidentTimelineEntry::query()
            ->with('user')
            ->where('incidentId', (string) $incident->id);
    }

    public function deleteForIncident(Incident $incident): void
    {
        IncidentTimelineEntry::query()->where('incidentId', (string) $incident->id)->delete();
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
