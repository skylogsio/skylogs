<?php

use App\Models\User;
use Tests\Support\Factories\AlertRuleFactory;

describe('AlertRule watch list membership', function () {
    it('reports whether a given user is watching the alert', function () {
        $alert = AlertRuleFactory::unsaved([
            'watchUserIds' => ['user-1'],
        ]);

        $watcher = new User;
        $watcher->setAttribute('id', 'user-1');
        $watcher->setAttribute('_id', 'user-1');

        $other = new User;
        $other->setAttribute('id', 'user-2');
        $other->setAttribute('_id', 'user-2');

        expect($alert->isWatched($watcher))->toBeTrue()
            ->and($alert->isWatched($other))->toBeFalse();
    });
});
