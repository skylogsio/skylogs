<?php

use App\Enums\IncidentStatus;
use App\Models\Incident;

describe('Incident acknowledgements', function () {
    it('looks up acknowledgement by team id', function () {
        $incident = new Incident;
        $incident->acknowledgements = [
            [
                'teamId' => '6512ab000000000000000001',
                'acknowledgedBy' => '6512ab000000000000000002',
                'acknowledgedAt' => '2026-08-19T10:00:00+00:00',
            ],
        ];

        expect($incident->hasTeamAcknowledged('6512ab000000000000000001'))->toBeTrue()
            ->and($incident->hasTeamAcknowledged('6512ab000000000000000099'))->toBeFalse()
            ->and($incident->acknowledgementForTeam('6512ab000000000000000001')['acknowledgedBy'])
            ->toBe('6512ab000000000000000002');
    });

    it('lists unacknowledged teams and remaining follow-through', function () {
        $incident = new Incident;
        $incident->status = IncidentStatus::Investigating;
        $incident->teamIds = ['6512ab000000000000000001', '6512ab000000000000000099'];
        $incident->acknowledgements = [
            [
                'teamId' => '6512ab000000000000000001',
                'acknowledgedBy' => '6512ab000000000000000002',
                'acknowledgedAt' => '2026-08-19T10:00:00+00:00',
            ],
        ];
        $incident->policySla = [
            'requireCommander' => true,
            'statusPageUpdateRequired' => true,
            'postmortemRequired' => true,
        ];

        expect($incident->unacknowledgedTeamIds())->toBe(['6512ab000000000000000099'])
            ->and($incident->hasAllTeamsAcknowledged())->toBeFalse()
            ->and($incident->remaining())->toMatchArray([
                'unacknowledgedTeamIds' => ['6512ab000000000000000099'],
                'commanderRequired' => true,
                'statusPageUpdateRequired' => true,
                'postmortemRequired' => true,
            ]);

        $incident->status = IncidentStatus::Resolved;

        expect($incident->remaining())->toBeNull();
    });
});
