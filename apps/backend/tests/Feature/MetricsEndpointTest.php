<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\EndpointType;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Models\AlertRule;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\NotificationDelivery;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Tests\Support\IncidentTestData;
use Tests\Support\TeamTestData;

describe('GET /metrics', function () {
    beforeEach(function () {
        config([
            'cache.default' => 'array',
            'ha.enabled' => false,
            'prometheus.allowed_ips' => [],
        ]);

        $this->mock(MasterSupervisorRepository::class, function ($mock) {
            $mock->shouldReceive('all')->andReturn([]);
        });
        $this->mock(WorkloadRepository::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn([]);
        });
        $this->mock(JobRepository::class, function ($mock) {
            $mock->shouldReceive('countRecentlyFailed')->andReturn(0);
            $mock->shouldReceive('countRecent')->andReturn(0);
        });
        $this->mock(MetricsRepository::class, function ($mock) {
            $mock->shouldReceive('jobsProcessedPerMinute')->andReturn(0);
        });

        $this->owner = TeamTestData::createUser(Constants::ROLE_OWNER);
        $this->team = TeamTestData::createTeam($this->owner);
        $this->alertIds = [];
        $this->deliveryIds = [];
        $this->healthIds = [];
        /** @var list<Incident> */
        $this->incidents = [];
    });

    afterEach(function () {
        NotificationDelivery::query()->whereIn('_id', $this->deliveryIds)->delete();
        HealthCheck::query()->whereIn('_id', $this->healthIds)->delete();
        AlertRule::query()->whereIn('_id', $this->alertIds)->delete();

        foreach ($this->incidents as $incident) {
            IncidentTestData::deleteIncident($incident);
        }

        TeamTestData::deleteTeam($this->team);
        TeamTestData::deleteUser($this->owner);
    });

    it('exports skylogs domain gauges and omits the placeholder gauge', function () {
        $alert = AlertRule::create([
            'name' => 'Metrics alert '.uniqid(),
            'type' => AlertRuleType::API,
            'userId' => $this->owner->id,
            'state' => AlertRule::CRITICAL,
            'isPrivate' => false,
        ]);
        $this->alertIds[] = $alert->_id;

        $incident = IncidentTestData::createIncident(
            (string) $this->owner->id,
            [(string) $this->team->id],
            [
                'status' => IncidentStatus::Open,
                'severity' => IncidentSeverity::Sev1,
            ],
        );
        $this->incidents[] = $incident;

        $delivery = NotificationDelivery::factory()->create([
            'status' => NotificationDeliveryStatus::FAILED,
            'endpointType' => EndpointType::TELEGRAM->value,
            'userId' => (string) $this->owner->id,
        ]);
        $this->deliveryIds[] = $delivery->_id;

        $health = HealthCheck::create([
            'alertRuleId' => $alert->_id,
            'state' => HealthCheck::DOWN,
            'counter' => 1,
        ]);
        $this->healthIds[] = $health->_id;

        $response = $this->get('/metrics');

        $response->assertSuccessful();

        $body = $response->getContent();

        expect($body)
            ->toContain('skylogs_alert_rules')
            ->toContain('type="api"')
            ->toContain('state="critical"')
            ->toContain('skylogs_incidents')
            ->toContain('status="open"')
            ->toContain('severity="SEV1"')
            ->toContain('skylogs_notification_deliveries')
            ->toContain('status="failed"')
            ->toContain('endpoint_type="telegram"')
            ->toContain('skylogs_health_checks')
            ->toContain('state="down"')
            ->toContain('skylogs_ha_enabled')
            ->toContain('skylogs_raft_up')
            ->not->toContain('My gauge')
            ->not->toContain('my_gauge');
    });
});
