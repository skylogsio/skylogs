<?php

use App\Enums\AlertRuleType;
use App\Enums\EndpointType;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\Notify;
use App\Services\Notification\DeliveryTarget;
use App\Services\SendNotifyService;
use App\Support\NotifyMessagePayload;
use Tests\Support\Factories\AlertRuleFactory;

/**
 * @param  list<string>  $endpointIds
 * @return list<DeliveryTarget>
 */
function invokeBuildTargets(Notify $notify, array $endpointIds): array
{
    $endpoints = collect($endpointIds)->map(function (string $id) {
        $endpoint = new Endpoint(['type' => EndpointType::TELEGRAM->value]);
        $endpoint->forceFill(['_id' => $id]);

        return $endpoint;
    });

    $method = new ReflectionMethod(SendNotifyService::class, 'buildTargets');
    $method->setAccessible(true);

    return $method->invoke(app(SendNotifyService::class), $notify, $endpoints);
}

function templatedAlertRule(): AlertRule
{
    return AlertRuleFactory::unsaved([
        'name' => 'CPU Alert',
        'type' => AlertRuleType::PROMETHEUS,
        'rules' => [
            [
                'id' => 'template-1',
                'type' => 'template',
                'template' => '{{name}} on {{label.pod}}',
                'endpointIds' => ['endpoint-1'],
            ],
        ],
    ]);
}

describe('SendNotifyService fixed system messages', function () {
    it('does not apply template behavior rules to fixed system messages', function (string $type, string $body) {
        $alertRule = templatedAlertRule();

        $notify = Notify::withoutEvents(fn () => new Notify([
            'type' => $type,
            'messages' => NotifyMessagePayload::fromBody($body)->toArray(),
            'alert' => $alertRule->toArray(),
        ]));
        $notify->setRelation('alertRule', $alertRule);

        $targets = invokeBuildTargets($notify, ['endpoint-1']);

        expect($targets)->toHaveCount(1)
            ->and($targets[0]->message->defaultMessage())->toBe($body)
            ->and($targets[0]->templateApplied)->toBeFalse();
    })->with([
        'test notification' => [SendNotifyJob::ALERT_RULE_TEST, 'Testing CPU Alert.'],
        'acknowledge notification' => [SendNotifyJob::ALERT_RULE_ACKNOWLEDGED, 'Jane Acknowledged CPU Alert.'],
    ]);

    it('applies template behavior rules only to the endpoints they cover', function () {
        $alertRule = templatedAlertRule();

        $notify = Notify::withoutEvents(fn () => new Notify([
            'type' => SendNotifyJob::PROMETHEUS_FIRE,
            'messages' => NotifyMessagePayload::fromBody('stored default')->toArray(),
            'alert' => [
                'state' => 2,
                'alerts' => [
                    [
                        'labels' => ['pod' => 'api-1'],
                        'annotations' => [],
                    ],
                ],
            ],
        ]));
        $notify->setRelation('alertRule', $alertRule);

        $targets = collect(invokeBuildTargets($notify, ['endpoint-1', 'endpoint-2']))
            ->keyBy(fn (DeliveryTarget $target) => (string) $target->endpoint->id);

        expect($targets['endpoint-1']->message->defaultMessage())->toBe('CPU Alert on api-1')
            ->and($targets['endpoint-1']->templateApplied)->toBeTrue()
            ->and($targets['endpoint-2']->message->defaultMessage())->toBe('stored default')
            ->and($targets['endpoint-2']->templateApplied)->toBeFalse();
    });
});
