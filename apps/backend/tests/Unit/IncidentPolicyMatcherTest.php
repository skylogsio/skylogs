<?php

use App\Enums\AlertRuleType;
use App\Enums\Constants;
use App\Enums\DataSourceType;
use App\Services\IncidentPolicy\AlertMatchContext;
use App\Services\IncidentPolicy\IncidentPolicyMatcher;
use Tests\Support\IncidentPolicyTestData;
use Tests\Support\TeamTestData;

describe('IncidentPolicyMatcher', function () {
    beforeEach(function () {
        $this->user = TeamTestData::createUser(Constants::ROLE_MEMBER);
        $this->policies = [];
        $this->alertRules = [];
        $this->matcher = app(IncidentPolicyMatcher::class);
        $this->alertRuleId = bin2hex(random_bytes(12));
        $this->otherAlertRuleId = bin2hex(random_bytes(12));
        $this->serviceId = bin2hex(random_bytes(12));
        $this->tag = 'tag-'.uniqid();
    });

    afterEach(function () {
        foreach ($this->policies as $policy) {
            IncidentPolicyTestData::deletePolicy($policy);
        }
        foreach ($this->alertRules as $alertRule) {
            IncidentPolicyTestData::deleteAlertRule($alertRule);
        }
        TeamTestData::deleteUser($this->user);
    });

    it('matches when the alert rule id is listed', function () {
        $policy = IncidentPolicyTestData::createPolicy([
            'match' => ['alertRuleIds' => [$this->alertRuleId]],
        ]);
        $this->policies[] = $policy;

        $matches = $this->matcher->matching(new AlertMatchContext(
            alertRuleId: $this->alertRuleId,
        ));

        expect($matches->pluck('id')->all())->toBe([$policy->id]);
    });

    it('matches when any listed filter hits, even if the others do not', function () {
        $policy = IncidentPolicyTestData::createPolicy([
            'match' => [
                'alertRuleIds' => [$this->otherAlertRuleId],
                'tags' => [$this->tag],
            ],
        ]);
        $this->policies[] = $policy;

        $matches = $this->matcher->matching(new AlertMatchContext(
            alertRuleId: $this->alertRuleId,
            tags: [$this->tag],
        ));

        expect($matches->pluck('id')->all())->toBe([$policy->id]);
    });

    it('matches by service id or data source type', function () {
        $uniqueTypeTag = 'prometheus-only-'.uniqid();
        $byService = IncidentPolicyTestData::createPolicy([
            'match' => ['serviceIds' => [$this->serviceId]],
        ]);
        $byType = IncidentPolicyTestData::createPolicy([
            'match' => [
                'tags' => [$uniqueTypeTag],
                'dataSourceTypes' => [DataSourceType::PROMETHEUS->value],
            ],
        ]);
        $this->policies[] = $byService;
        $this->policies[] = $byType;

        expect($this->matcher->matching(new AlertMatchContext(
            serviceIds: [$this->serviceId],
        ))->pluck('id')->all())->toBe([$byService->id]);

        expect($this->matcher->matching(new AlertMatchContext(
            tags: [$uniqueTypeTag],
            dataSourceType: DataSourceType::PROMETHEUS->value,
        ))->pluck('id')->all())->toContain($byType->id);
    });

    it('returns every policy that matches the same alert', function () {
        $first = IncidentPolicyTestData::createPolicy([
            'match' => ['tags' => [$this->tag]],
        ]);
        $second = IncidentPolicyTestData::createPolicy([
            'match' => ['tags' => [$this->tag, 'other-'.uniqid()]],
        ]);
        $this->policies[] = $first;
        $this->policies[] = $second;

        $ids = $this->matcher->matching(new AlertMatchContext(tags: [$this->tag]))
            ->pluck('id')
            ->all();

        expect($ids)->toHaveCount(2)
            ->and($ids)->toContain($first->id)
            ->and($ids)->toContain($second->id);
    });

    it('does not match when no filter overlaps', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'match' => [
                'alertRuleIds' => [$this->alertRuleId],
                'tags' => [$this->tag],
            ],
        ]);

        expect($this->matcher->matching(new AlertMatchContext(
            alertRuleId: $this->otherAlertRuleId,
            tags: ['no-overlap-'.uniqid()],
        )))->toHaveCount(0);
    });

    it('skips disabled policies and those with autoCreate off', function () {
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'enabled' => false,
            'match' => ['tags' => [$this->tag]],
        ]);
        $this->policies[] = IncidentPolicyTestData::createPolicy([
            'match' => ['tags' => [$this->tag]],
            'incident' => ['autoCreate' => false],
        ]);

        expect($this->matcher->matching(new AlertMatchContext(tags: [$this->tag])))->toHaveCount(0);
    });

    it('builds context from an alert rule including overlapping data source types', function () {
        $alertRule = IncidentPolicyTestData::createAlertRule($this->user);
        $alertRule->update([
            'type' => AlertRuleType::PROMETHEUS,
            'tags' => [$this->tag],
        ]);
        $this->alertRules[] = $alertRule->fresh();

        $policy = IncidentPolicyTestData::createPolicy([
            'match' => ['tags' => [$this->tag], 'dataSourceTypes' => [DataSourceType::PROMETHEUS->value]],
        ]);
        $this->policies[] = $policy;

        $matches = $this->matcher->matching(AlertMatchContext::fromAlertRule($alertRule->fresh()));

        expect($matches->pluck('id')->all())->toContain($policy->id);
    });
});
