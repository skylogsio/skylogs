<?php

namespace Tests\Support;

use App\Enums\RunbookSourceType;
use App\Enums\RunbookStatus;
use App\Models\Runbook;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

class RunbookTestData
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function createRunbook(User $author, Team $team, array $overrides = []): Runbook
    {
        $name = $overrides['name'] ?? 'test-runbook-'.uniqid();

        return Runbook::create([
            'name' => $name,
            'slug' => $overrides['slug'] ?? Str::slug($name),
            'description' => $overrides['description'] ?? 'test runbook',
            'teamIds' => $overrides['teamIds'] ?? [$team->id],
            'tags' => $overrides['tags'] ?? [],
            'status' => $overrides['status'] ?? RunbookStatus::Published,
            'sourceType' => $overrides['sourceType'] ?? RunbookSourceType::Steps,
            'content' => $overrides['content'] ?? null,
            'externalUrl' => $overrides['externalUrl'] ?? null,
            'steps' => $overrides['steps'] ?? [
                ['title' => 'Check the dashboard', 'description' => null, 'command' => null, 'expectedResult' => null],
            ],
            'appliesTo' => $overrides['appliesTo'] ?? [
                'serviceIds' => [],
                'alertRuleIds' => [],
                'tags' => [],
                'severities' => [],
            ],
            'reviewIntervalDays' => $overrides['reviewIntervalDays'] ?? null,
            'version' => 1,
            'createdBy' => $author->id,
            'updatedBy' => $author->id,
        ]);
    }

    public static function deleteRunbook(Runbook $runbook): void
    {
        Runbook::query()->where('_id', $runbook->id)->delete();
    }

    public static function deleteRunbookByName(string $name): void
    {
        Runbook::query()->where('name', $name)->delete();
    }
}
