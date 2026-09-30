<?php

use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\EndpointService;
use Tests\Support\TeamTestData;

/**
 * @param  array<string, mixed>  $overrides
 */
function createAssignableEndpoint(User $owner, array $overrides = []): Endpoint
{
    return Endpoint::create(array_merge([
        'userId' => $owner->id,
        'name' => 'Assignable '.uniqid(),
        'type' => EndpointType::TELEGRAM->value,
        'chatId' => '-100'.uniqid(),
        'isPublic' => false,
        'accessUserIds' => [],
        'accessTeamIds' => [],
    ], $overrides));
}

describe('assigning accessible endpoints to alerts', function () {
    beforeEach(function () {
        config(['cache.default' => 'array']);
        app(EndpointService::class)->flushCache();

        $this->owner = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->member = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->stranger = TeamTestData::createUser(Constants::ROLE_MEMBER);

        $this->team = TeamTestData::createTeam(
            $this->owner,
            [$this->owner->id, $this->member->id],
        );

        $this->ownedEndpoint = createAssignableEndpoint($this->member);
        $this->userSharedEndpoint = createAssignableEndpoint($this->stranger, [
            'accessUserIds' => [$this->member->id],
        ]);
        $this->teamSharedEndpoint = createAssignableEndpoint($this->stranger, [
            'accessTeamIds' => [$this->team->id],
        ]);
        $this->privateEndpoint = createAssignableEndpoint($this->stranger);

        $this->userAlert = AlertRule::create([
            'name' => 'Endpoint assign user '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'userIds' => [$this->member->id],
            'teamIds' => [],
            'endpointIds' => [],
            'isPrivate' => true,
        ]);

        $this->teamAlert = AlertRule::create([
            'name' => 'Endpoint assign team '.uniqid(),
            'type' => 'api',
            'userId' => $this->owner->id,
            'userIds' => [],
            'teamIds' => [$this->team->id],
            'endpointIds' => [],
            'isPrivate' => true,
        ]);
    });

    afterEach(function () {
        Endpoint::query()->whereIn('_id', [
            $this->ownedEndpoint->id,
            $this->userSharedEndpoint->id,
            $this->teamSharedEndpoint->id,
            $this->privateEndpoint->id,
        ])->delete();

        AlertRule::query()->whereIn('_id', [
            $this->userAlert->id,
            $this->teamAlert->id,
        ])->delete();

        TeamTestData::deleteTeam($this->team);

        foreach (['owner', 'member', 'stranger'] as $property) {
            TeamTestData::deleteUser($this->{$property});
        }
    });

    it('adds owned and shared endpoints from notify for a user listed on the alert', function () {
        $this->actingAs($this->member, 'api')
            ->putJson('/api/v1/alert-rule-notify/'.$this->userAlert->id, [
                'endpointIds' => [
                    $this->ownedEndpoint->id,
                    $this->userSharedEndpoint->id,
                    $this->teamSharedEndpoint->id,
                    $this->privateEndpoint->id,
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->userAlert->refresh();

        $endpointIds = array_map(strval(...), $this->userAlert->endpointIds ?? []);

        expect($endpointIds)->toContain((string) $this->ownedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->userSharedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->teamSharedEndpoint->id)
            ->and($endpointIds)->not->toContain((string) $this->privateEndpoint->id);
    });

    it('adds owned and shared endpoints from notify for a team shared alert', function () {
        $this->actingAs($this->member, 'api')
            ->putJson('/api/v1/alert-rule-notify/'.$this->teamAlert->id, [
                'endpointIds' => [
                    $this->ownedEndpoint->id,
                    $this->userSharedEndpoint->id,
                    $this->teamSharedEndpoint->id,
                ],
            ])
            ->assertSuccessful();

        $this->teamAlert->refresh();

        $endpointIds = array_map(strval(...), $this->teamAlert->endpointIds ?? []);

        expect($endpointIds)->toContain((string) $this->ownedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->userSharedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->teamSharedEndpoint->id);
    });

    it('keeps another users endpoint when the alert owner updates the alert', function () {
        $manager = TeamTestData::createUser(Constants::ROLE_MANAGER);
        $alert = AlertRule::create([
            'name' => 'Endpoint keep '.uniqid(),
            'type' => 'api',
            'userId' => $manager->id,
            'userIds' => [$this->member->id],
            'teamIds' => [],
            'endpointIds' => [(string) $this->ownedEndpoint->id],
            'isPrivate' => true,
        ]);

        try {
            $this->actingAs($manager, 'api')
                ->putJson('/api/v1/alert-rule/'.$alert->id, [
                    'name' => 'renamed by owner',
                    'endpointIds' => [],
                ])
                ->assertSuccessful()
                ->assertJsonPath('status', true);

            $alert->refresh();

            expect($alert->name)->toBe('renamed by owner')
                ->and(array_map(strval(...), $alert->endpointIds ?? []))->toContain((string) $this->ownedEndpoint->id);
        } finally {
            AlertRule::query()->where('_id', $alert->id)->delete();
            TeamTestData::deleteUser($manager);
        }
    });

    it('removes deselected endpoints the editor can use when the alert is updated', function () {
        $this->userAlert->endpointIds = [
            (string) $this->ownedEndpoint->id,
            (string) $this->teamSharedEndpoint->id,
            (string) $this->privateEndpoint->id,
        ];
        $this->userAlert->save();

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->userAlert->id, [
                'name' => 'deselect endpoints',
                'endpointIds' => [(string) $this->ownedEndpoint->id],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->userAlert->refresh();

        $endpointIds = array_map(strval(...), $this->userAlert->endpointIds ?? []);

        expect($endpointIds)->not->toContain((string) $this->teamSharedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->ownedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->privateEndpoint->id);
    });

    it('only stores endpoints the creator can use when an alert is created', function () {
        $name = 'Endpoint create '.uniqid();

        try {
            $this->actingAs($this->member, 'api')
                ->postJson('/api/v1/alert-rule', [
                    'name' => $name,
                    'type' => 'api',
                    'endpointIds' => [
                        (string) $this->ownedEndpoint->id,
                        (string) $this->teamSharedEndpoint->id,
                        (string) $this->privateEndpoint->id,
                    ],
                ])
                ->assertSuccessful()
                ->assertJsonPath('status', true);

            $endpointIds = array_map(strval(...), AlertRule::where('name', $name)->firstOrFail()->endpointIds ?? []);

            expect($endpointIds)->toContain((string) $this->ownedEndpoint->id)
                ->and($endpointIds)->toContain((string) $this->teamSharedEndpoint->id)
                ->and($endpointIds)->not->toContain((string) $this->privateEndpoint->id);
        } finally {
            AlertRule::query()->where('name', $name)->delete();
        }
    });

    it('does not let the alert owner add an endpoint they cannot use when updating the alert', function () {
        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->userAlert->id, [
                'name' => 'add endpoints',
                'endpointIds' => [
                    (string) $this->teamSharedEndpoint->id,
                    (string) $this->privateEndpoint->id,
                ],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->userAlert->refresh();

        $endpointIds = array_map(strval(...), $this->userAlert->endpointIds ?? []);

        expect($endpointIds)->toContain((string) $this->teamSharedEndpoint->id)
            ->and($endpointIds)->not->toContain((string) $this->privateEndpoint->id);
    });

    it('leaves alert endpoints untouched when the update omits endpointIds', function () {
        $this->userAlert->endpointIds = [
            (string) $this->teamSharedEndpoint->id,
            (string) $this->privateEndpoint->id,
        ];
        $this->userAlert->save();

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->userAlert->id, [
                'name' => 'no endpoint change',
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->userAlert->refresh();

        expect(array_map(strval(...), $this->userAlert->endpointIds ?? []))->toBe([
            (string) $this->teamSharedEndpoint->id,
            (string) $this->privateEndpoint->id,
        ]);
    });

    it('ignores malformed endpoint ids when updating the alert', function () {
        $this->userAlert->endpointIds = [(string) $this->teamSharedEndpoint->id];
        $this->userAlert->save();

        $this->actingAs($this->owner, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->userAlert->id, [
                'name' => 'malformed endpoints',
                'endpointIds' => [['nested'], (string) $this->teamSharedEndpoint->id],
            ])
            ->assertSuccessful()
            ->assertJsonPath('status', true);

        $this->userAlert->refresh();

        expect(array_map(strval(...), $this->userAlert->endpointIds ?? []))->toBe([
            (string) $this->teamSharedEndpoint->id,
        ]);
    });

    it('rejects alert edits from a user who only has access to the alert', function () {
        $this->actingAs($this->member, 'api')
            ->putJson('/api/v1/alert-rule/'.$this->userAlert->id, [
                'name' => 'should stay unchanged',
                'endpointIds' => [(string) $this->ownedEndpoint->id],
            ])
            ->assertForbidden();

        $this->userAlert->refresh();

        expect($this->userAlert->endpointIds ?? [])->toBe([]);
    });

    it('shows every assigned endpoint and only allows removal of endpoints the member can use', function () {
        $this->userAlert->endpointIds = [
            (string) $this->ownedEndpoint->id,
            (string) $this->privateEndpoint->id,
        ];
        $this->userAlert->save();

        $alertEndpoints = collect(
            $this->actingAs($this->member, 'api')
                ->getJson('/api/v1/alert-rule-notify/'.$this->userAlert->id)
                ->assertSuccessful()
                ->json('alertEndpoints')
        );

        $privatePayload = $alertEndpoints->firstWhere('id', (string) $this->privateEndpoint->id);

        expect($alertEndpoints->pluck('id')->all())->toContain((string) $this->ownedEndpoint->id)
            ->and($alertEndpoints->pluck('id')->all())->toContain((string) $this->privateEndpoint->id)
            ->and($alertEndpoints->firstWhere('id', (string) $this->ownedEndpoint->id)['canRemove'])->toBeTrue()
            ->and($privatePayload['canRemove'])->toBeFalse()
            ->and($privatePayload)->not->toHaveKey('value')
            ->and($privatePayload)->not->toHaveKey('chatId');

        $this->actingAs($this->member, 'api')
            ->deleteJson('/api/v1/alert-rule-notify/'.$this->userAlert->id.'/'.$this->privateEndpoint->id)
            ->assertForbidden();

        $this->actingAs($this->member, 'api')
            ->deleteJson('/api/v1/alert-rule-notify/'.$this->userAlert->id.'/'.$this->ownedEndpoint->id)
            ->assertSuccessful();

        $this->userAlert->refresh();

        $endpointIds = array_map(strval(...), $this->userAlert->endpointIds ?? []);

        expect($endpointIds)->not->toContain((string) $this->ownedEndpoint->id)
            ->and($endpointIds)->toContain((string) $this->privateEndpoint->id);
    });

    it('lets the alert owner remove any endpoint assigned to the alert', function () {
        $this->userAlert->endpointIds = [(string) $this->privateEndpoint->id];
        $this->userAlert->save();

        $this->actingAs($this->owner, 'api')
            ->deleteJson('/api/v1/alert-rule-notify/'.$this->userAlert->id.'/'.$this->privateEndpoint->id)
            ->assertSuccessful();

        $this->userAlert->refresh();

        expect(array_map(strval(...), $this->userAlert->endpointIds ?? []))->not->toContain((string) $this->privateEndpoint->id);
    });

    it('rejects behavior rule edits from a user who only has access to the alert', function () {
        $this->actingAs($this->member, 'api')
            ->postJson('/api/v1/alert-rule-behavior-rule/'.$this->userAlert->id, [
                'name' => 'Extra notify',
                'type' => 'notification',
                'filters' => [
                    ['key' => 'instance', 'value' => 'api'],
                ],
                'endpointIds' => [(string) $this->ownedEndpoint->id],
            ])
            ->assertForbidden();
    });

    it('does not attach endpoints when the user cannot access the alert', function () {
        $this->actingAs($this->stranger, 'api')
            ->putJson('/api/v1/alert-rule-notify/'.$this->userAlert->id, [
                'endpointIds' => [(string) $this->privateEndpoint->id],
            ])
            ->assertSuccessful();

        $this->userAlert->refresh();

        expect($this->userAlert->endpointIds ?? [])->toBe([]);
    });
});
