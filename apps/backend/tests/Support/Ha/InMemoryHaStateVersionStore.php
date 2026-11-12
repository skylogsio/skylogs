<?php

namespace Tests\Support\Ha;

use App\Models\AlertRule;
use App\Services\Ha\HaStateVersionStore;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Stands in for the MongoDB backed counter so ordering and lifecycle rules can
 * be exercised without a database.
 */
final class InMemoryHaStateVersionStore extends HaStateVersionStore
{
    /**
     * @var array<string, array{version: int, nodeId: string, state: string|null, updatedAt: DateTimeInterface}>
     */
    private array $entries = [];

    private string $nodeId = 'node-1';

    public function seed(string $key, int $version, string $nodeId, ?string $state = null, ?DateTimeInterface $updatedAt = null): void
    {
        $this->entries[$key] = [
            'version' => $version,
            'nodeId' => $nodeId,
            'state' => $state,
            'updatedAt' => $updatedAt ?? CarbonImmutable::now(),
        ];
    }

    public function next(string $key, ?string $state = null): int
    {
        $version = ($this->entries[$key]['version'] ?? 0) + 1;

        $this->seed($key, $version, $this->nodeId, $state);

        return $version;
    }

    public function current(string $key): int
    {
        return $this->entries[$key]['version'] ?? 0;
    }

    public function entry(string $key): array
    {
        return [
            'version' => $this->entries[$key]['version'] ?? 0,
            'nodeId' => $this->entries[$key]['nodeId'] ?? '',
        ];
    }

    public function record(string $key, int $version, string $nodeId, ?string $state = null): void
    {
        $this->seed($key, $version, $nodeId, $state);
    }

    public function keysWithPrefix(string $prefix): array
    {
        return array_values(array_filter(
            $this->allKeys(),
            fn (string $key): bool => str_starts_with($key, $prefix),
        ));
    }

    public function unresolvedKeysWithPrefix(string $prefix): array
    {
        return array_values(array_filter(
            $this->keysWithPrefix($prefix),
            fn (string $key): bool => $this->entries[$key]['state'] !== AlertRule::RESOlVED,
        ));
    }

    public function allKeys(): array
    {
        return array_keys($this->entries);
    }

    public function resolvedKeysUpdatedBefore(DateTimeInterface $before): array
    {
        return array_values(array_keys(array_filter(
            $this->entries,
            fn (array $entry): bool => $entry['state'] === AlertRule::RESOlVED
                && $entry['updatedAt'] < $before,
        )));
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }
}
