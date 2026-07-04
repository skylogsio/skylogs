<?php

namespace App\Services\AlertStatus;

use App\Models\AlertRule;
use Illuminate\Support\Collection;

/**
 * Turns a chronological list of status-changing events for a single alert rule
 * into a fixed number of equal-width buckets covering [fromTime, toTime], then
 * merges consecutive buckets that belong to the same underlying status period.
 *
 * Each output segment carries a bucket-slot count (summing to the configured
 * timeline slot count) so the frontend can render a fixed-width bar as N colored blocks without losing
 * exact incident boundaries or summaries from the underlying history.
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
        AlertRule::RESOlVED => 1,
        AlertRule::UNKNOWN => 0,
    ];

    /**
     * @param  Collection<int, AlertStatusEvent>  $events  baseline event (if any) plus every event inside the window, unsorted
     * @return array{bucketSeconds: int, segments: array<int, array{status: string, count: int, fromTime: int, toTime: int, summary?: string}>}
     */
    public function build(Collection $events, int $fromTime, int $toTime, int $bucketCount): array
    {
        $exactSegments = $this->buildExactSegments($events, $fromTime, $toTime);

        $duration = max(1, $toTime - $fromTime);
        $actualBucketCount = max(1, $bucketCount);
        $bucketSeconds = max(1, (int) ceil($duration / $actualBucketCount));

        $bucketAssignments = [];

        for ($i = 0; $i < $actualBucketCount; $i++) {
            $bucketFrom = $fromTime + ($i * $bucketSeconds);
            $bucketTo = ($i === $actualBucketCount - 1)
                ? $toTime
                : $fromTime + (($i + 1) * $bucketSeconds);

            if ($bucketFrom >= $bucketTo) {
                break;
            }

            $overlapping = $this->overlappingExactSegments($exactSegments, $bucketFrom, $bucketTo);
            $winningIndex = $this->winningExactSegmentIndex($overlapping);

            $bucketAssignments[] = [
                'exactSegmentIndex' => $winningIndex,
                'fromTime' => $bucketFrom,
                'toTime' => $bucketTo,
            ];
        }

        return [
            'bucketSeconds' => $bucketSeconds,
            'segments' => $this->mergeBucketAssignments($bucketAssignments, $exactSegments),
        ];
    }

    /**
     * @param  array<int, array{status: string, from: int, to: int, count: int, summary: ?string}>  $exactSegments
     * @return array<int, array{index: int, segment: array{status: string, from: int, to: int, count: int, summary: ?string}}>
     */
    private function overlappingExactSegments(array $exactSegments, int $bucketFrom, int $bucketTo): array
    {
        $overlapping = [];

        foreach ($exactSegments as $index => $segment) {
            if ($segment['from'] < $bucketTo && $segment['to'] > $bucketFrom) {
                $overlapping[] = [
                    'index' => $index,
                    'segment' => $segment,
                ];
            }
        }

        return $overlapping;
    }

    /**
     * @param  array<int, array{index: int, segment: array{status: string, from: int, to: int, count: int, summary: ?string}}>  $overlapping
     */
    private function winningExactSegmentIndex(array $overlapping): int
    {
        if ($overlapping === []) {
            return -1;
        }

        return collect($overlapping)
            ->sortBy([
                fn (array $entry) => -(self::STATUS_PRIORITY[$entry['segment']['status']] ?? 0),
                fn (array $entry) => -$entry['segment']['from'],
            ])
            ->first()['index'];
    }

    /**
     * @param  array<int, array{exactSegmentIndex: int, fromTime: int, toTime: int}>  $bucketAssignments
     * @param  array<int, array{status: string, from: int, to: int, count: int, summary: ?string}>  $exactSegments
     * @return array<int, array{status: string, count: int, fromTime: int, toTime: int, summary?: string}>
     */
    private function mergeBucketAssignments(array $bucketAssignments, array $exactSegments): array
    {
        if ($bucketAssignments === []) {
            return [];
        }

        $merged = [];
        $currentIndex = $bucketAssignments[0]['exactSegmentIndex'];
        $currentFrom = $bucketAssignments[0]['fromTime'];
        $currentTo = $bucketAssignments[0]['toTime'];
        $currentCount = 1;

        for ($i = 1; $i < count($bucketAssignments); $i++) {
            $bucket = $bucketAssignments[$i];

            if ($bucket['exactSegmentIndex'] === $currentIndex) {
                $currentTo = $bucket['toTime'];
                $currentCount++;

                continue;
            }

            $merged[] = $this->toOutputSegment($currentIndex, $currentFrom, $currentTo, $currentCount, $exactSegments);

            $currentIndex = $bucket['exactSegmentIndex'];
            $currentFrom = $bucket['fromTime'];
            $currentTo = $bucket['toTime'];
            $currentCount = 1;
        }

        $merged[] = $this->toOutputSegment($currentIndex, $currentFrom, $currentTo, $currentCount, $exactSegments);

        return $merged;
    }

    /**
     * @param  array<int, array{status: string, from: int, to: int, count: int, summary: ?string}>  $exactSegments
     * @return array{status: string, count: int, fromTime: int, toTime: int, summary?: string}
     */
    private function toOutputSegment(
        int $exactSegmentIndex,
        int $fromTime,
        int $toTime,
        int $count,
        array $exactSegments,
    ): array {
        $status = AlertRule::UNKNOWN;
        $summary = null;

        if ($exactSegmentIndex >= 0 && isset($exactSegments[$exactSegmentIndex])) {
            $status = $exactSegments[$exactSegmentIndex]['status'];
            $summary = $exactSegments[$exactSegmentIndex]['summary'];
        }

        $segment = [
            'status' => $status,
            'count' => $count,
            'fromTime' => $fromTime,
            'toTime' => $toTime,
        ];

        if ($summary !== null) {
            $segment['summary'] = $summary;
        }

        return $segment;
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
}
