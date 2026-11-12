<?php

use App\Enums\Constants;
use App\Models\OnCallPlan;
use Tests\Support\TeamTestData;

describe('OnCallPlan relations', function () {
    it('belongs to a team via teamId', function () {
        $user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $team = TeamTestData::createTeam($user);

        $plan = OnCallPlan::create([
            'teamId' => $team->id,
            'name' => 'test-plan-'.uniqid(),
            'timezone' => 'UTC',
            'layers' => [],
        ]);

        expect($plan->team)->not->toBeNull()
            ->and($plan->team->id)->toBe($team->id);

        $freshTeam = $team->fresh();
        expect($freshTeam->onCallPlan)->not->toBeNull()
            ->and($freshTeam->onCallPlan->id)->toBe($plan->id);

        OnCallPlan::query()->where('_id', $plan->id)->delete();
        TeamTestData::deleteTeam($team);
        TeamTestData::deleteUser($user);
    });

    it('returns null when a team has no on-call plan', function () {
        $user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $team = TeamTestData::createTeam($user);

        expect($team->onCallPlan)->toBeNull();

        TeamTestData::deleteTeam($team);
        TeamTestData::deleteUser($user);
    });
});
