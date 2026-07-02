<?php

namespace App\Services\AlertStatus;

use App\Models\AlertRule;
use Illuminate\Support\Collection;

/**
 * Turns a chronological list of status-changing events for a single alert rule
 * into a fixed number of equal-width buckets covering [fromTime, toTime].
 *
 * Each bucket is colored by the worst status that occurred anywhere inside it
 * (critical > warning > unknown > resolved) and carries every raw underlying
 * event that overlaps it, so the frontend can show exact incident detail on
 * hover/click without losing the fixed-width bar layout.
 */
final class AlertStatusTimelineBuilder
{
    /**
     * Status priority when multiple statuses overlap the same bucket - higher wins.
     *
     * @var array<string, int>
     */
    private const STATUS_PRIORITY = [
        AlertRule::CRITICAL => 3,
        AlertRule::WARNING => 2,
        AlertRule::UNKNOWN => 1,
        AlertRule::RESOlVED => 0,
    ];

    public function __construct(
        private readonly int $minBucketSeconds = 100,
    ) {}

    /**
     * @param  Collection<int, AlertStatusEvent>  $events  baseline event (if any) plus every event inside the window, unsorted
     * @return array{bucketSeconds: int, segments: array<int, array<string, mixed>>}
     */
    public function build(Collection $events, int $fromTime, int $toTime, int $bucketCount): array
    {
        $exactSegments = $this->buildExactSegments($events, $fromTime, $toTime);

        $duration = max(1, $toTime - $fromTime);
        $bucketSeconds = max($this->minBucketSeconds, (int) ceil($duration / max(1, $bucketCount)));
        $actualBucketCount = max(1, (int) ceil($duration / $bucketSeconds));

        $segments = [];
        for ($i = 0; $i < $actualBucketCount; $i++) {
            $bucketFrom = $fromTime + ($i * $bucketSeconds);
            $bucketTo = min($toTime, $bucketFrom + $bucketSeconds);

            if ($bucketFrom >= $bucketTo) {
                break;
            }

            $overlapping = array_values(array_filter(
                $exactSegments,
                fn (array $segment) => $segment['from'] < $bucketTo && $segment['to'] > $bucketFrom,
            ));

            $segments[] = [
                'status' => $this->worstStatus($overlapping),
                'fromTime' => $bucketFrom,
                'toTime' => $bucketTo,
                'changesCount' => count($overlapping),
                'events' => array_map(fn (array $segment) => [
                    'status' => $segment['status'],
                    'fromTime' => $segment['from'],
                    'toTime' => $segment['to'],
                    'count' => $segment['count'],
                    'summary' => $segment['summary'],
                ], $overlapping),
            ];
        }

        return [
            'bucketSeconds' => $bucketSeconds,
            'segments' => $segments,
        ];
    }

    /**
     * Reconstructs the exact-boundary status timeline from the baseline + windowed
     * events: each event's status holds from its own occurrence until the next
     * event (or the end of the window).
     *
     * @param  Collection<int, AlertStatusEvent>  $events
     * @return array<int, array{status: string, from: int, to: int, count: int, summary: ?string}>
     */
    private function buildExactSegments(Collection $events, int $fromTime, int $toTime): array
    {
        $points = $events
            ->sortBy(fn (AlertStatusEvent $event) => $event->occurredAt->getTimestamp())
            ->values()
            ->map(fn (AlertStatusEvent $event) => [
                'status' => $event->status,
                'count' => $event->count,
                'summary' => $event->summary,
                'at' => max($fromTime, $event->occurredAt->getTimestamp()),
            ])
            ->all();

        if ($points === [] || $points[0]['at'] > $fromTime) {
            array_unshift($points, [
                'status' => AlertRule::UNKNOWN,
                'count' => 0,
                'summary' => null,
                'at' => $fromTime,
            ]);
        }

        $segments = [];
        $total = count($points);

        foreach ($points as $index => $point) {
            $end = $index + 1 < $total ? $points[$index + 1]['at'] : $toTime;

            if ($end <= $point['at']) {
                continue;
            }

            $segments[] = [
                'status' => $point['status'],
                'from' => $point['at'],
                'to' => $end,
                'count' => $point['count'],
                'summary' => $point['summary'],
            ];
        }

        return $segments;
    }

    /**
     * @param  array<int, array{status: string, from: int, to: int, count: int, summary: ?string}>  $overlapping
     */
    private function worstStatus(array $overlapping): string
    {
        if ($overlapping === []) {
            return AlertRule::UNKNOWN;
        }

        return collect($overlapping)
            ->pluck('status')
            ->sortByDesc(fn (string $status) => self::STATUS_PRIORITY[$status] ?? 0)
            ->first();
    }
}
