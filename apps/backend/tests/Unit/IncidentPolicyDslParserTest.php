<?php

use App\Services\IncidentPolicy\IncidentPolicyDslParser;
use App\Services\IncidentPolicy\IncidentPolicyDslParseResult;

function parseDsl(string $yaml): IncidentPolicyDslParseResult
{
    return app(IncidentPolicyDslParser::class)->parse($yaml);
}

/**
 * @return list<string>
 */
function dslErrorPaths(IncidentPolicyDslParseResult $result): array
{
    return array_map(fn ($error) => $error->path, $result->errors);
}

function validPolicyYaml(): string
{
    return <<<'YAML'
    apiVersion: skylogs.io/v1
    kind: IncidentPolicy
    metadata:
      name: payments-critical
      description: Response policy for payment-path incidents
      teams: [payments, platform]
    spec:
      match:
        alertRules: [payments-api-5xx]
        tags: [payments]
      grouping:
        key: [serviceId, alertRuleId]
        windowMinutes: 10
      incident:
        autoCreate: true
        defaultSeverity: SEV3
        severityMap:
          critical: SEV1
          warning: SEV3
      rules:
        - severity: SEV1
          ack: { withinMinutes: 5 }
          resolve: { withinMinutes: 60 }
          requireCommander: true
          notify:
            channels: [endpoint:oncall-sms]
          escalation:
            useLayers: false
          postmortem:
            required: true
            reviewRequired: true
          runbooks: [payments-api-5xx-triage]
        - severity: SEV3
          ack: { withinMinutes: 30 }
    YAML;
}

describe('IncidentPolicyDslParser', function () {
    it('parses a valid definition and keeps references as names', function () {
        $result = parseDsl(validPolicyYaml());

        expect($result->isValid())->toBeTrue()
            ->and($result->policies)->toHaveCount(1);

        $policy = $result->policies[0];

        expect($policy->name)->toBe('payments-critical')
            ->and($policy->teamRefs)->toBe(['payments', 'platform'])
            ->and($policy->enabled)->toBeTrue()
            ->and($policy->match['alertRules'])->toBe(['payments-api-5xx'])
            ->and($policy->match['services'])->toBe([])
            ->and($policy->grouping)->toBe(['key' => ['serviceId', 'alertRuleId'], 'windowMinutes' => 10])
            ->and($policy->incident['severityMap'])->toBe(['critical' => 'SEV1', 'warning' => 'SEV3'])
            ->and(array_keys($policy->rules))->toBe(['SEV1', 'SEV3'])
            ->and($policy->rules['SEV1']['notifyChannels'])->toBe(['endpoint:oncall-sms'])
            ->and($policy->rules['SEV1']['escalation']['useLayers'])->toBeFalse()
            ->and($policy->rules['SEV1']['runbookNames'])->toBe(['payments-api-5xx-triage']);
    });

    it('applies DSL defaults', function () {
        $policy = parseDsl(validPolicyYaml())->policies[0];

        expect($policy->rules['SEV1']['postmortem']['dueDays'])
            ->toBe(IncidentPolicyDslParser::DEFAULT_POSTMORTEM_DUE_DAYS)
            ->and($policy->rules['SEV3']['escalation']['useLayers'])->toBeTrue()
            ->and($policy->rules['SEV3']['requireCommander'])->toBeFalse()
            ->and($policy->rules['SEV3']['postmortem']['required'])->toBeFalse()
            ->and($policy->rules['SEV3']['postmortem']['dueDays'])->toBeNull()
            ->and($policy->incident['autoResolveOnAlertClear'])->toBeFalse();
    });

    it('defaults the grouping window when grouping is omitted', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: minimal
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV2
        YAML);

        expect($result->isValid())->toBeTrue()
            ->and($result->policies[0]->grouping)->toBe([
                'key' => [],
                'windowMinutes' => IncidentPolicyDslParser::DEFAULT_GROUPING_WINDOW_MINUTES,
            ]);
    });

    it('rejects an unsupported apiVersion and kind', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v2
        kind: Runbook
        metadata:
          name: wrong-envelope
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV2
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('apiVersion', 'kind');
    });

    it('rejects an on-call plan reference on the policy', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: linked-plan
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
              escalation:
                onCallPlan: payments-primary
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('spec.rules[0].escalation.onCallPlan')
            ->and($result->errors[0]->message)->toContain("Unknown field 'onCallPlan'");
    });

    it('reports unknown fields by path', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: typo-policy
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
              postmortem:
                requred: true
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('spec.rules[0].postmortem.requred')
            ->and($result->errors[0]->message)->toContain("Unknown field 'requred'");
    });

    it('requires at least one matcher', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: matches-everything
          teams: [payments]
        spec:
          match: {}
          rules:
            - severity: SEV1
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('spec.match');
    });

    it('rejects duplicate severities and an invalid severity', function () {
        $duplicate = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: duplicate-severity
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
            - severity: SEV1
        YAML);

        $invalid = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: invalid-severity
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV9
        YAML);

        expect(dslErrorPaths($duplicate))->toContain('spec.rules[1].severity')
            ->and(dslErrorPaths($invalid))->toContain('spec.rules[0].severity');
    });

    it('rejects a resolve target shorter than the ack target', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: impossible-sla
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
              ack: { withinMinutes: 30 }
              resolve: { withinMinutes: 10 }
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('spec.rules[0].resolve.withinMinutes');
    });

    it('rejects a name that is not slug shaped', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: Payments Critical
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
        YAML);

        expect(dslErrorPaths($result))->toContain('metadata.name');
    });

    it('reports invalid YAML syntax without throwing', function () {
        $result = parseDsl("apiVersion: skylogs.io/v1\n\tkind: IncidentPolicy");

        expect($result->isValid())->toBeFalse()
            ->and($result->errors[0]->path)->toBe('document')
            ->and($result->errors[0]->message)->toStartWith('Invalid YAML:');
    });

    it('parses multiple documents and prefixes error paths with the document index', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: first
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
        ---
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: second
          teams: [platform]
        spec:
          match:
            tags: [platform]
          rules:
            - severity: SEV2
              ack: { withinMinutes: 0 }
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and($result->policies)->toHaveCount(1)
            ->and($result->policies[0]->name)->toBe('first')
            ->and(dslErrorPaths($result))->toContain('documents[1].spec.rules[0].ack.withinMinutes');
    });

    it('rejects two policies with the same name in one input', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: same-name
          teams: [payments]
        spec:
          match:
            tags: [payments]
          rules:
            - severity: SEV1
        ---
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: same-name
          teams: [platform]
        spec:
          match:
            tags: [platform]
          rules:
            - severity: SEV2
        YAML);

        expect($result->isValid())->toBeFalse()
            ->and(dslErrorPaths($result))->toContain('documents[1].metadata.name');
    });

    it('accepts data source types from DataSourceType', function () {
        $result = parseDsl(<<<'YAML'
        apiVersion: skylogs.io/v1
        kind: IncidentPolicy
        metadata:
          name: logs-policy
          teams: [payments]
        spec:
          match:
            dataSourceTypes: [victoria_logs, splunk]
          rules:
            - severity: SEV2
        YAML);

        expect($result->isValid())->toBeTrue()
            ->and($result->policies[0]->match['dataSourceTypes'])->toBe(['victoria_logs', 'splunk']);
    });

    it('rejects empty input', function () {
        expect(parseDsl("# just a comment\n")->isValid())->toBeFalse()
            ->and(parseDsl('')->errors[0]->message)->toBe('The YAML input is empty.');
    });
});
