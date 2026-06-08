<?php

use App\Models\AlertRule;
use App\Services\NotifyMessageComposer;
use App\Support\NotifyMessagePayload;
use Tests\Support\Factories\AlertRuleFactory;
use Tests\Support\Messageables\PlainTextMessageable;
use Tests\Support\Messageables\StructuredPayloadMessageable;
use Tests\Support\Messageables\TelegramInlineKeyboardMessageable;

describe('NotifyMessagePayload', function () {
    it('stores a canonical body with channel overrides', function () {
        $payload = NotifyMessagePayload::fromBody('hello', [
            'telegram' => ['message' => 'hello', 'meta' => []],
            'call' => 'Alert fired',
        ]);

        expect($payload->toArray())->toBe([
            'body' => 'hello',
            'overrides' => [
                'telegram' => ['message' => 'hello', 'meta' => []],
                'call' => 'Alert fired',
            ],
        ])
            ->and($payload->smsMessage())->toBe('hello')
            ->and($payload->callMessage())->toBe('Alert fired');
    });

    it('reads legacy eight-key stored messages', function () {
        $payload = NotifyMessagePayload::fromStored([
            'defaultMessage' => 'full body',
            'telegram' => [
                'message' => 'full body',
                'meta' => [['text' => 'Acknowledge', 'url' => 'https://example.test/ack']],
            ],
            'callMessage' => 'Alert fired',
            'smsMessage' => 'full body',
        ]);

        expect($payload->defaultMessage())->toBe('full body')
            ->and($payload->telegram())->toBeArray()
            ->and($payload->callMessage())->toBe('Alert fired')
            ->and($payload->smsMessage())->toBe('full body');
    });
});

describe('NotifyMessageComposer', function () {
    it('builds compact payload from fromMessageable', function () {
        $alert = new PlainTextMessageable('hello-world');

        $payload = NotifyMessageComposer::fromMessageable($alert);

        expect($payload->toArray())->toBe([
            'body' => 'hello-world',
            'overrides' => [],
        ])
            ->and($payload->smsMessage())->toBe('hello-world')
            ->and($payload->telegram())->toBe('hello-world');
    });

    it('delegates buildMessages to fromMessageable when alert rule is null', function () {
        $alert = new PlainTextMessageable('no-rule');

        $messages = NotifyMessageComposer::buildMessages(null, $alert);

        expect($messages)->toBe([
            'body' => 'no-rule',
            'overrides' => [],
        ]);
    });

    it('delegates buildMessages to fromMessageable for alert rules', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'R1',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('fallback');

        $messages = NotifyMessageComposer::buildMessages($rule, $alert);

        expect($messages['body'])->toBe('fallback');
    });

    it('applies one template string to the canonical body', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU High',
            'state' => AlertRule::CRITICAL,
            'fireCount' => 3,
        ]);
        $alert = new StructuredPayloadMessageable('worker-7');

        $payload = NotifyMessageComposer::composeFromSingleTemplate(
            $rule,
            $alert,
            '{{name}}|{{state}}|{{fireCount}}|{{alert.instance}}',
        );

        expect($payload->defaultMessage())->toBe('CPU High|critical|3|worker-7')
            ->and($payload->smsMessage())->toBe('CPU High|critical|3|worker-7')
            ->and($payload->teamsMessage())->toBe('CPU High|critical|3|worker-7');
    });

    it('renders unknown placeholders as empty', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'N',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('x');

        $payload = NotifyMessageComposer::composeFromSingleTemplate(
            $rule,
            $alert,
            '{{name}}{{not_a_real_key}}',
        );

        expect($payload->smsMessage())->toBe('N');
    });

    it('uses template text for telegram when telegram() returns a string', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'RuleA',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('ignored-for-telegram-channel');

        $payload = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'TG:{{name}}');

        expect($payload->telegram())->toBe('TG:RuleA');
    });

    it('preserves telegram inline keyboard meta when applying template', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'GrafanaLike',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new TelegramInlineKeyboardMessageable('old-body');

        $payload = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'Firing: {{name}}');

        expect($payload->telegram())->toBeArray()
            ->and($payload->telegram()['message'])->toBe('Firing: GrafanaLike')
            ->and($payload->telegram())->toHaveKey('meta')
            ->and($payload->telegram()['meta'][0]['text'] ?? null)->toBe('Acknowledge')
            ->and($payload->telegram()['meta'][0]['url'] ?? null)->toBe('https://example.test/ack/1');
    });

    it('captures call and telegram overrides from messageable alerts', function () {
        $alert = new TelegramInlineKeyboardMessageable('old-body');

        $payload = NotifyMessagePayload::fromMessageable($alert);

        expect($payload->defaultMessage())->toBe('default')
            ->and($payload->telegram())->toBeArray()
            ->and($payload->callMessage())->toBe('default');
    });
});
