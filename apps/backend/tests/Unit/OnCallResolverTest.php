<?php

use App\Enums\Constants;
use App\Services\OnCallResolver;
use Illuminate\Support\Carbon;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\OnCallPlanTestData;
use Tests\Support\TeamTestData;

describe('OnCallResolver', function () {
    beforeEach(function () {
        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->backup = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->user->update(['name' => 'Primary-'.uniqid()]);
        $this->backup->update(['name' => 'Backup-'.uniqid()]);
        $this->team = TeamTestData::createTeam($this->user, [$this->user->id, $this->backup->id]);
        $this->endpoint = IncidentPolicyTestData::createEndpoint($this->user, 'primary-sms');
        $this->endpoint->update(['onCall' => true]);

        $this->plan = IncidentPolicyTestData::createOnCallPlan($this->team, 'resolver-plan');
        $this->plan->update([
            'timezone' => 'Asia/Tehran',
            'layers' => [
                [
                    'level' => 1,
                    'escalateAfterMinutes' => 5,
                    'entries' => [[
                        'userId' => $this->user->id,
                        'windows' => [
                            ['daysOfWeek' => [1], 'startTime' => '00:00', 'endTime' => '08:00'],
                            ['daysOfWeek' => [1], 'startTime' => '08:00', 'endTime' => '16:00'],
                            ['daysOfWeek' => [7], 'startTime' => '00:00', 'endTime' => '24:00'],
                        ],
                    ]],
                ],
                [
                    'level' => 2,
                    'escalateAfterMinutes' => 15,
                    'entries' => [[
                        'userId' => $this->backup->id,
                        'windows' => [
                            ['daysOfWeek' => [1, 2, 3, 4, 5, 6, 7], 'startTime' => '00:00', 'endTime' => '24:00'],
                        ],
                    ]],
                ],
            ],
        ]);
        $this->plan->refresh();
    });

    afterEach(function () {
        OnCallPlanTestData::deletePlan($this->plan);
        IncidentPolicyTestData::deleteEndpoint($this->endpoint);
        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->user);
        TeamTestData::deleteUser($this->backup);
    });

    it('matches Monday morning in the plan timezone', function () {
        $result = app(OnCallResolver::class)->at(
            $this->plan,
            Carbon::parse('2026-08-31T03:30:00Z'),
        );

        expect($result['layers'][0]['onCall']['userId'])->toBe($this->user->id)
            ->and($result['layers'][0]['onCall']['window']['endTime'])->toBe('08:00')
            ->and($result['layers'][0]['onCall']['endpoint']['id'])->toBe($this->endpoint->id)
            ->and($result['layers'][1]['onCall']['userId'])->toBe($this->backup->id);
    });

    it('treats 08:00 as the start of the next window', function () {
        $result = app(OnCallResolver::class)->at(
            $this->plan,
            Carbon::parse('2026-08-31T04:30:00Z'),
        );

        expect($result['layers'][0]['onCall']['window']['startTime'])->toBe('08:00');
    });

    it('covers Sunday with a 24:00 exclusive end', function () {
        $result = app(OnCallResolver::class)->at(
            $this->plan,
            Carbon::parse('2026-08-30T17:00:00Z'),
        );

        expect($result['at'])->toContain('2026-08-30T20:30:00')
            ->and($result['layers'][0]['onCall']['userId'])->toBe($this->user->id);
    });

    it('returns a gap when the weekday has no window', function () {
        $result = app(OnCallResolver::class)->at(
            $this->plan,
            Carbon::parse('2026-09-01T08:00:00Z'),
        );

        expect($result['layers'][0]['onCall'])->toBeNull()
            ->and($result['layers'][1]['onCall']['userId'])->toBe($this->backup->id);
    });
});
