<?php

use App\Enums\AlertRuleAccessLevel;
use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Team;
use App\Models\User;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\Support\Factories\AlertRuleFactory;

function makeAccessTestUser(string $id, bool $isAdmin = false): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn($isAdmin);
    $user->setAttribute('id', $id);
    $user->setAttribute('_id', $id);

    return $user;
}

function makeAccessTestAlert(array $attributes = []): AlertRule
{
    return AlertRuleFactory::unsaved(array_merge([
        'type' => AlertRuleType::PROMETHEUS,
        'userId' => 'owner-id',
        'userIds' => [],
        'teamIds' => [],
    ], $attributes));
}

function makeAlertRuleServiceWithTeams(Collection $teams): AlertRuleService
{
    $teamService = Mockery::mock(TeamService::class);
    $teamService->shouldReceive('userTeams')->andReturn($teams);

    return new AlertRuleService($teamService);
}

describe('AlertRuleService access', function () {
    it('grants admin access to any alert', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $admin = makeAccessTestUser('admin-id', isAdmin: true);
        $alert = makeAccessTestAlert(['userId' => 'owner-id']);

        expect($service->hasAdminAccessAlert($admin, $alert))->toBeTrue()
            ->and($service->hasUserAccessAlert($admin, $alert))->toBeTrue();
    });

    it('grants owner admin and user access', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $owner = makeAccessTestUser('owner-id');
        $alert = makeAccessTestAlert(['userId' => 'owner-id']);

        expect($service->hasAdminAccessAlert($owner, $alert))->toBeTrue()
            ->and($service->hasUserAccessAlert($owner, $alert))->toBeTrue();
    });

    it('grants user access when listed in alert userIds by id', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert(['userIds' => ['member-id']]);

        expect($service->hasAdminAccessAlert($member, $alert))->toBeFalse()
            ->and($service->hasUserAccessAlert($member, $alert))->toBeTrue();
    });

    it('grants user access when alert is shared with a team the user belongs to', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');
        $team->setAttribute('_id', 'team-1');

        $service = makeAlertRuleServiceWithTeams(collect([$team]));
        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert(['teamIds' => ['team-1']]);

        expect($service->hasTeamAccessAlert($member, $alert))->toBeTrue()
            ->and($service->hasUserAccessAlert($member, $alert))->toBeTrue()
            ->and($service->hasAdminAccessAlert($member, $alert))->toBeFalse();
    });

    it('denies user access when alert teamIds do not overlap user teams', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');
        $team->setAttribute('_id', 'team-1');

        $service = makeAlertRuleServiceWithTeams(collect([$team]));
        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert(['teamIds' => ['team-2']]);

        expect($service->hasTeamAccessAlert($stranger, $alert))->toBeFalse()
            ->and($service->hasUserAccessAlert($stranger, $alert))->toBeFalse();
    });

    it('denies user access when user has no teams', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert(['teamIds' => ['team-1']]);

        expect($service->hasTeamAccessAlert($stranger, $alert))->toBeFalse()
            ->and($service->hasUserAccessAlert($stranger, $alert))->toBeFalse();
    });

    it('denies user access when alert has no teamIds', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');

        $service = makeAlertRuleServiceWithTeams(collect([$team]));
        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert(['teamIds' => []]);

        expect($service->hasTeamAccessAlert($member, $alert))->toBeFalse();
    });

    it('adds teamIds filter for non-admin list queries when user belongs to teams', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');

        $service = makeAlertRuleServiceWithTeams(collect([$team]));
        $user = makeAccessTestUser('member-id');

        $this->actingAs($user);

        $match = [];
        $service->getMatchFilterArray(Request::create('/'), $match);

        expect($match['$or'])->toContain(['userId' => 'member-id'])
            ->and($match['$or'])->toContain(['userIds' => 'member-id'])
            ->and($match['$or'])->toContain(['teamIds' => ['$in' => ['team-1']]]);
    });

    it('does not add teamIds filter when user has no teams', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $user = makeAccessTestUser('member-id');

        $this->actingAs($user);

        $match = [];
        $service->getMatchFilterArray(Request::create('/'), $match);

        expect($match['$or'])->toBe([
            ['userId' => 'member-id'],
            ['userIds' => 'member-id'],
        ]);
    });

    it('grants readonly access to non-private alerts for strangers', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert(['isPrivate' => false]);

        expect($service->hasReadAccessAlert($stranger, $alert))->toBeTrue()
            ->and($service->resolveAccessLevel($stranger, $alert))->toBe(AlertRuleAccessLevel::Readonly);
    });

    it('denies readonly access to private alerts for strangers', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert(['isPrivate' => true]);

        expect($service->hasReadAccessAlert($stranger, $alert))->toBeFalse()
            ->and($service->resolveAccessLevel($stranger, $alert))->toBe(AlertRuleAccessLevel::None);
    });

    it('treats alerts without isPrivate as organization-visible', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert();

        expect($service->isPrivateAlert($alert))->toBeFalse()
            ->and($service->hasReadAccessAlert($stranger, $alert))->toBeTrue();
    });

    it('adds organization scope filter for non-admin list queries', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $user = makeAccessTestUser('member-id');

        $this->actingAs($user);

        $match = [];
        $service->getMatchFilterArray(
            Request::create('/', 'GET', ['scope' => 'organization']),
            $match
        );

        expect($match['$or'])->toContain(['isPrivate' => ['$ne' => true]]);
    });

    it('preserves access $or when filtering by userId for non-admins', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');

        $service = makeAlertRuleServiceWithTeams(collect([$team]));
        $user = makeAccessTestUser('member-id');

        $this->actingAs($user);

        $match = [];
        $service->getMatchFilterArray(
            Request::create('/', 'GET', ['userId' => 'other-user-id']),
            $match
        );

        expect($match)->not->toHaveKey('$or')
            ->and($match)->not->toHaveKey('userId')
            ->and($match['$and'])->toHaveCount(2)
            ->and($match['$and'][0]['$or'])->toContain(['userId' => 'member-id'])
            ->and($match['$and'][0]['$or'])->toContain(['userIds' => 'member-id'])
            ->and($match['$and'][0]['$or'])->toContain(['teamIds' => ['$in' => ['team-1']]])
            ->and($match['$and'][1]['$or'])->toBe([
                ['userId' => 'other-user-id'],
                ['userIds' => 'other-user-id'],
            ]);
    });

    it('applies userId filter without access $or for admins', function () {
        $service = makeAlertRuleServiceWithTeams(collect());
        $admin = makeAccessTestUser('admin-id', isAdmin: true);

        $this->actingAs($admin);

        $match = [];
        $service->getMatchFilterArray(
            Request::create('/', 'GET', ['userId' => 'other-user-id']),
            $match
        );

        expect($match)->not->toHaveKey('$and')
            ->and($match)->not->toHaveKey('userId')
            ->and($match['$or'])->toBe([
                ['userId' => 'other-user-id'],
                ['userIds' => 'other-user-id'],
            ]);
    });
});

describe('EndpointService alert access', function () {
    it('uses global selectable endpoints for team-shared alerts', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');

        $teamService = Mockery::mock(TeamService::class);
        $teamService->shouldReceive('userTeams')->andReturn(collect([$team]));

        $alertRuleService = new AlertRuleService($teamService);
        $endpointService = Mockery::mock(EndpointService::class, [$teamService, $alertRuleService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert([
            'userId' => 'owner-id',
            'teamIds' => ['team-1'],
        ]);
        $globalEndpoints = collect([(object) ['id' => 'shared-endpoint-1']]);

        $endpointService->shouldReceive('rememberGlobalSelectableEndpoints')
            ->once()
            ->with($member)
            ->andReturn($globalEndpoints);

        expect($endpointService->selectableUserEndpoint($member, $alert))->toBe($globalEndpoints);
    });

    it('uses global selectable endpoints for userIds-shared alerts', function () {
        $teamService = Mockery::mock(TeamService::class);
        $teamService->shouldReceive('userTeams')->andReturn(collect());

        $alertRuleService = new AlertRuleService($teamService);
        $endpointService = Mockery::mock(EndpointService::class, [$teamService, $alertRuleService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert([
            'userId' => 'owner-id',
            'userIds' => ['member-id'],
        ]);
        $globalEndpoints = collect([(object) ['id' => 'shared-endpoint-1']]);

        $endpointService->shouldReceive('rememberGlobalSelectableEndpoints')
            ->once()
            ->with($member)
            ->andReturn($globalEndpoints);

        expect($endpointService->selectableUserEndpoint($member, $alert))->toBe($globalEndpoints);
    });

    it('uses global selectable endpoints for alert owners', function () {
        $teamService = Mockery::mock(TeamService::class);
        $alertRuleService = new AlertRuleService($teamService);
        $endpointService = Mockery::mock(EndpointService::class, [$teamService, $alertRuleService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $owner = makeAccessTestUser('owner-id');
        $alert = makeAccessTestAlert(['userId' => 'owner-id']);
        $globalEndpoints = collect([(object) ['id' => 'endpoint-1']]);

        $endpointService->shouldReceive('rememberGlobalSelectableEndpoints')
            ->once()
            ->with($owner)
            ->andReturn($globalEndpoints);

        expect($endpointService->selectableUserEndpoint($owner, $alert))->toBe($globalEndpoints);
    });

    it('does not treat userIds-shared access as team access', function () {
        $teamService = Mockery::mock(TeamService::class);
        $teamService->shouldReceive('userTeams')->andReturn(collect());

        $alertRuleService = new AlertRuleService($teamService);
        $member = makeAccessTestUser('member-id');
        $alert = makeAccessTestAlert([
            'userId' => 'owner-id',
            'userIds' => ['member-id'],
        ]);

        expect($alertRuleService->userIsListedOnAlert($member, $alert))->toBeTrue()
            ->and($alertRuleService->hasTeamAccessAlert($member, $alert))->toBeFalse()
            ->and($alertRuleService->hasUserAccessAlert($member, $alert))->toBeTrue();
    });

    it('treats owned, user-shared, and team-shared endpoints as usable', function () {
        $team = new Team;
        $team->setAttribute('id', 'team-1');
        $team->setAttribute('_id', 'team-1');

        $teamService = Mockery::mock(TeamService::class);
        $teamService->shouldReceive('userTeams')->andReturn(collect([$team]));

        $endpointService = new EndpointService($teamService, new AlertRuleService($teamService));
        $member = makeAccessTestUser('member-id');

        $owned = new Endpoint;
        $owned->userId = 'member-id';
        $owned->accessUserIds = [];
        $owned->accessTeamIds = [];

        $userShared = new Endpoint;
        $userShared->userId = 'other-id';
        $userShared->accessUserIds = ['member-id'];
        $userShared->accessTeamIds = [];

        $teamShared = new Endpoint;
        $teamShared->userId = 'other-id';
        $teamShared->accessUserIds = [];
        $teamShared->accessTeamIds = ['team-1'];

        $private = new Endpoint;
        $private->userId = 'other-id';
        $private->accessUserIds = [];
        $private->accessTeamIds = ['team-2'];
        $private->value = 'secret-phone';
        $private->chatId = 'secret-chat';
        $private->botToken = 'secret-token';

        $alert = makeAccessTestAlert(['userId' => 'owner-id', 'userIds' => ['member-id']]);
        $presented = $endpointService->presentAlertEndpoint($member, $alert, $private);

        expect($presented)->not->toHaveKey('value')
            ->and($presented)->not->toHaveKey('chatId')
            ->and($presented)->not->toHaveKey('botToken')
            ->and($presented['canRemove'])->toBeFalse()
            ->and($endpointService->presentAlertEndpoint($member, $alert, $owned))->toHaveKey('userId')
            ->and($endpointService->userCanRemoveAlertEndpoint($member, $alert, $owned))->toBeTrue()
            ->and($endpointService->userCanRemoveAlertEndpoint($member, $alert, $private))->toBeFalse()
            ->and($endpointService->userCanRemoveAlertEndpoint(makeAccessTestUser('owner-id'), $alert, $private))->toBeTrue()
            ->and($endpointService->userCanUseEndpoint($member, $owned))->toBeTrue()
            ->and($endpointService->userCanUseEndpoint($member, $userShared))->toBeTrue()
            ->and($endpointService->userCanUseEndpoint($member, $teamShared))->toBeTrue()
            ->and($endpointService->userCanUseEndpoint($member, $private))->toBeFalse();
    });

    it('returns empty collection when user has no alert access', function () {
        $teamService = Mockery::mock(TeamService::class);
        $teamService->shouldReceive('userTeams')->andReturn(collect());

        $alertRuleService = new AlertRuleService($teamService);
        $endpointService = new EndpointService($teamService, $alertRuleService);

        $stranger = makeAccessTestUser('stranger-id');
        $alert = makeAccessTestAlert(['userId' => 'owner-id']);

        expect($endpointService->selectableUserEndpoint($stranger, $alert))->toBeEmpty();
    });
});
