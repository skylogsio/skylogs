<?php

use App\Enums\AlertRuleType;
use App\Jobs\SendNotifyJob;
use App\Models\Notify;
use App\Services\SendNotifyService;
use App\Support\NotifyMessagePayload;
use Tests\Support\Factories\AlertRuleFactory;

/**
 * @return array{messageable: mixed, notify: Notify}
 */
function invokeMessageableForTemplate(Notify $notify, string $template): array
{
    $service = app(SendNotifyService::class);
    $method = new ReflectionMethod(SendNotifyService::class, 'messageableForTemplate');
    $method->setAccessible(true);

    return [
        'messageable' => $method->invoke($service, $notify, $template),
        'notify' => $notify,
    ];
}

describe('SendNotifyService fixed system messages', function () {
    it('does not apply template behavior rules for test notifications', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $notify = Notify::withoutEvents(fn () => new Notify([
            'type' => SendNotifyJob::ALERT_RULE_TEST,
            'messages' => NotifyMessagePayload::fromBody('Testing CPU Alert.')->toArray(),
            'alert' => $alertRule->toArray(),
        ]));
        $notify->setRelation('alertRule', $alertRule);

        $result = invokeMessageableForTemplate($notify, '{{name}} on {{label.pod}}');

        expect($result['messageable'])->toBe($notify)
            ->and($result['messageable']->defaultMessage())->toBe('Testing CPU Alert.');
    });

    it('does not apply template behavior rules for acknowledge notifications', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

        $notify = Notify::withoutEvents(fn () => new Notify([
            'type' => SendNotifyJob::ALERT_RULE_ACKNOWLEDGED,
            'messages' => NotifyMessagePayload::fromBody('Jane Acknowledged CPU Alert.')->toArray(),
            'alert' => $alertRule->toArray(),
        ]));
        $notify->setRelation('alertRule', $alertRule);

        $result = invokeMessageableForTemplate($notify, '{{alert_items labels="*"}}');

        expect($result['messageable'])->toBe($notify)
            ->and($result['messageable']->defaultMessage())->toBe('Jane Acknowledged CPU Alert.');
    });

    it('still applies template behavior rules for real alert notifications', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
        ]);

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

        $result = invokeMessageableForTemplate($notify, '{{name}} on {{label.pod}}');

        expect($result['messageable'])->not->toBe($notify)
            ->and($result['messageable']->defaultMessage())->toBe('CPU Alert on api-1');
    });

    it('sendChannelAlerts uses the stored notify body for test messages', function () {
        $alertRule = AlertRuleFactory::unsaved([
            'name' => 'CPU Alert',
            'type' => AlertRuleType::PROMETHEUS,
            'rules' => [
                [
                    'id' => 'template-1',
                    'type' => 'template',
                    'template' => '{{name}} SHOULD NOT APPEAR ALONE',
                    'endpointIds' => ['endpoint-1'],
                ],
            ],
        ]);

        $notify = Notify::withoutEvents(fn () => new Notify([
            'type' => SendNotifyJob::ALERT_RULE_TEST,
            'messages' => NotifyMessagePayload::fromBody('Testing CPU Alert.')->toArray(),
            'alert' => $alertRule->toArray(),
        ]));
        $notify->setRelation('alertRule', $alertRule);

        $endpoints = collect([
            (object) ['id' => 'endpoint-1', '_id' => 'endpoint-1'],
        ]);

        $service = app(SendNotifyService::class);
        $method = new ReflectionMethod(SendNotifyService::class, 'sendChannelAlerts');
        $method->setAccessible(true);

        $capturedMessageable = null;
        $method->invoke(
            $service,
            $endpoints,
            $notify,
            function ($group, $messageable) use (&$capturedMessageable) {
                $capturedMessageable = $messageable;

                return 'sent';
            },
        );

        expect($capturedMessageable)->toBe($notify)
            ->and($capturedMessageable->defaultMessage())->toBe('Testing CPU Alert.');
    });
});
