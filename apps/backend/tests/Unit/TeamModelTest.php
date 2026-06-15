<?php

use App\Enums\Constants;
use App\Models\Team;
use Tests\Support\TeamTestData;

describe('Team members accessor', function () {
    it('returns an empty list when userIds is empty', function () {
        $team = new Team;
        $team->setAttribute('userIds', []);

        expect($team->members)->toBe([]);
    });

    it('returns member names from stored user ids', function () {
        $user = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $team = new Team;
        $team->setAttribute('userIds', [$user->id]);

        expect($team->members)->toContain($user->name);

        TeamTestData::deleteUser($user);
    });
});
