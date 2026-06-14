<?php

use App\Models\Team;
use App\Models\User;

describe('Team members accessor', function () {
    it('returns an empty list when userIds is empty', function () {
        $team = new Team;
        $team->setAttribute('userIds', []);

        expect($team->members)->toBe([]);
    });

    it('returns member names from stored user ids', function () {
        $user = User::query()->first();

        if ($user === null) {
            $this->markTestSkipped('MongoDB user data required.');
        }

        $team = new Team;
        $team->setAttribute('userIds', [$user->id]);

        expect($team->members)->toContain($user->name);
    });
});
