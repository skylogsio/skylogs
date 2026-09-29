<?php

use App\Enums\AlertRuleType;
use App\Enums\EndpointType;
use App\Models\AlertInstance;
use App\Models\GrafanaWebhookAlert;
use App\Models\PrometheusCheck;
use App\Support\NotifyMessagePayload;
use Tests\Support\Factories\AlertRuleFactory;

function firingPrometheusCheck(bool $showAcknowledgeBtn): PrometheusCheck
{
    $rule = AlertRuleFactory::unsaved([
        'name' => 'CPU Alert',
        'type' => AlertRuleType::PROMETHEUS,
        'showAcknowledgeBtn' => $showAcknowledgeBtn,
    ]);

    $check = PrometheusCheck::withoutEvents(function () {
        $model = new PrometheusCheck;
        $model->forceFill([
            'state' => PrometheusCheck::FIRE,
            'alertRuleId' => '507f1f77bcf86cd799439011',
            'alertRuleName' => 'CPU Alert',
            'alerts' => [],
        ]);

        return $model;
    });
    $check->setRelation('alertRule', $rule);

    return $check;
}

describe('messageFor per source model and endpoint type', function () {
    it('adds the acknowledge button to prometheus telegram and bale content', function () {
        $check = firingPrometheusCheck(showAcknowledgeBtn: true);

        foreach ([EndpointType::TELEGRAM, EndpointType::BALE] as $type) {
            $content = $check->messageFor($type);

            expect($content)->toBeArray()
                ->and($content['message'])->toBe($check->defaultMessage())
                ->and($content['meta'][0]['text'])->toBe('Acknowledge')
                ->and($content['meta'][0]['url'])->toContain('507f1f77bcf86cd799439011');
        }
    });

    it('omits the acknowledge button when the rule disables it', function () {
        $content = firingPrometheusCheck(showAcknowledgeBtn: false)->messageFor(EndpointType::TELEGRAM);

        expect($content)->toBeArray()->not->toHaveKey('meta');
    });

    it('uses a short summary for prometheus calls', function () {
        expect(firingPrometheusCheck(showAcknowledgeBtn: false)->messageFor(EndpointType::CALL))
            ->toBe('Alert CPU Alert fired');
    });

    it('falls back to the default body for channels the model does not customize', function () {
        $check = firingPrometheusCheck(showAcknowledgeBtn: true);

        expect($check->messageFor(EndpointType::SMS))->toBeNull()
            ->and($check->messageFor(EndpointType::TEAMS))->toBeNull();
    });

    it('uses a short summary for grafana and api alert calls', function () {
        $rule = AlertRuleFactory::unsaved(['name' => 'Grafana Rule']);

        $grafana = GrafanaWebhookAlert::withoutEvents(fn () => new GrafanaWebhookAlert);
        $grafana->forceFill(['status' => GrafanaWebhookAlert::RESOLVED]);
        $grafana->setRelation('alertRule', $rule);

        $instance = AlertInstance::withoutEvents(fn () => new AlertInstance);
        $instance->forceFill(['alertRuleName' => 'Api Rule', 'state' => AlertInstance::NOTIFICATION]);

        expect($grafana->messageFor(EndpointType::CALL))->toBe('Alert Grafana Rule resolved')
            ->and($instance->messageFor(EndpointType::CALL))->toBe('Alert Api Rule notified');
    });

    it('snapshots telegram, bale and call content for a prometheus check', function () {
        $check = firingPrometheusCheck(showAcknowledgeBtn: true);

        $payload = NotifyMessagePayload::fromMessageable($check);

        expect(array_keys($payload->toArray()['overrides']))->toBe(['telegram', 'bale', 'call'])
            ->and($payload->forChannel(EndpointType::SMS))->toBe($check->defaultMessage());
    });
});
