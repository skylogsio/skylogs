<?php

namespace App\Services;

use App\Enums\IncidentTimelineEntryType;
use App\Enums\PostMortemStatus;
use App\Models\Incident;
use App\Models\PostMortem;
use App\Models\User;
use Carbon\Carbon;

/**
 * The postmortem of an incident, of which there is at most one.
 *
 * Visibility is inherited from the incident, so this service only owns the shape of the
 * document and the transition into `published`.
 */
class PostMortemService
{
    public function __construct(private readonly IncidentTimelineService $timelineService) {}

    public function forIncident(Incident $incident): ?PostMortem
    {
        return PostMortem::query()
            ->with('authorUser')
            ->where('incidentId', (string) $incident->id)
            ->first();
    }

    /**
     * Creates the postmortem on first write and replaces it afterwards: the endpoint is a
     * PUT, so an omitted field is an instruction to clear it, not to leave it alone.
     *
     * @param  array<string, mixed>  $validated
     */
    public function upsert(User $user, Incident $incident, array $validated): PostMortem
    {
        $existing = $this->forIncident($incident);
        $status = PostMortemStatus::from($validated['status'] ?? PostMortemStatus::Draft->value);

        $attributes = [
            'incidentId' => (string) $incident->id,
            'status' => $status,
            'summary' => (string) ($validated['summary'] ?? ''),
            'impact' => $validated['impact'] ?? null,
            'detection' => $validated['detection'] ?? null,
            'resolution' => $validated['resolution'] ?? null,
            'rootCause' => $this->normalizeRootCause($validated['rootCause'] ?? []),
            'whatWentWell' => $this->stringList($validated['whatWentWell'] ?? []),
            'whatWentWrong' => $this->stringList($validated['whatWentWrong'] ?? []),
            'lessonsLearned' => $this->stringList($validated['lessonsLearned'] ?? []),
            'reviewerIds' => $this->stringList($validated['reviewerIds'] ?? []),
            'dueAt' => $this->parseDate($validated['dueAt'] ?? null),
        ];

        if ($existing === null) {
            $postMortem = PostMortem::create([
                ...$attributes,
                'authorId' => $validated['authorId'] ?? $user->id,
                'publishedAt' => null,
            ]);
        } else {
            $existing->update([
                ...$attributes,
                'authorId' => $validated['authorId'] ?? $existing->authorId ?? $user->id,
            ]);
            $postMortem = $existing;
        }

        if ($status === PostMortemStatus::Published) {
            return $this->publish($user, $incident, $postMortem);
        }

        $postMortem->load('authorUser');

        return $postMortem;
    }

    /**
     * Idempotent: publishing an already published postmortem keeps the original date and
     * does not add a second timeline entry.
     */
    public function publish(User $user, Incident $incident, PostMortem $postMortem): PostMortem
    {
        if ($postMortem->publishedAt === null) {
            $postMortem->update([
                'status' => PostMortemStatus::Published,
                'publishedAt' => now(),
            ]);

            $this->timelineService->recordSystemEntry(
                $incident,
                IncidentTimelineEntryType::PostMortemPublished,
                'Postmortem published.',
                $user,
                ['postMortemId' => (string) $postMortem->id],
            );
        } elseif ($postMortem->status !== PostMortemStatus::Published) {
            $postMortem->update(['status' => PostMortemStatus::Published]);
        }

        $postMortem->load('authorUser');

        return $postMortem;
    }

    /**
     * @param  array<string, mixed>  $rootCause
     * @return array{method: string|null, whys: list<string>, contributingFactors: list<string>, statement: string|null}
     */
    private function normalizeRootCause(array $rootCause): array
    {
        return [
            'method' => $rootCause['method'] ?? null,
            'whys' => $this->stringList($rootCause['whys'] ?? []),
            'contributingFactors' => $this->stringList($rootCause['contributingFactors'] ?? []),
            'statement' => $rootCause['statement'] ?? null,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $value) => trim((string) $value), $values),
            fn (string $value) => $value !== '',
        ));
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
