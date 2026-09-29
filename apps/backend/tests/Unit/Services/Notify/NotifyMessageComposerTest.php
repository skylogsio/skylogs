<?php

use App\Concerns\ProvidesChannelMessages;
use App\Enums\EndpointType;
use App\Interfaces\Messageable;
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
            ->and($payload->forChannel(EndpointType::SMS))->toBe('hello')
            ->and($payload->forChannel(EndpointType::CALL))->toBe('Alert fired');
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
            ->and($payload->forChannel(EndpointType::TELEGRAM))->toBeArray()
            ->and($payload->forChannel(EndpointType::CALL))->toBe('Alert fired')
            ->and($payload->forChannel(EndpointType::SMS))->toBe('full body');
    });

    it('reads compact stored messages back unchanged', function () {
        $stored = [
            'body' => 'b',
            'overrides' => ['discord' => 'discord text'],
        ];

        $payload = NotifyMessagePayload::fromStored($stored);

        expect($payload->toArray())->toBe($stored)
            ->and($payload->forChannel(EndpointType::DISCORD))->toBe('discord text')
            ->and($payload->forChannel(EndpointType::EMAIL))->toBe('b');
    });

    it('returns plain text for chat content via textFor', function () {
        $payload = NotifyMessagePayload::fromBody('body', [
            'telegram' => ['message' => 'tg text', 'meta' => [['text' => 'Ack', 'url' => 'u']]],
        ]);

        expect($payload->textFor(EndpointType::TELEGRAM))->toBe('tg text')
            ->and($payload->textFor(EndpointType::SMS))->toBe('body');
    });

    it('snapshots model content for every registered channel', function () {
        $alert = new class implements Messageable
        {
            use ProvidesChannelMessages;

            public function defaultMessage(): string
            {
                return 'full body';
            }

            public function messageFor(EndpointType $type): array|string|null
            {
                return match ($type) {
                    EndpointType::SMS => 'short sms',
                    EndpointType::DISCORD => '**discord**',
                    EndpointType::EMAIL => 'full body',
                    default => null,
                };
            }
        };

        $payload = NotifyMessagePayload::fromMessageable($alert);

        expect($payload->toArray()['overrides'])->toBe([
            'sms' => 'short sms',
            'discord' => '**discord**',
        ])
            ->and($payload->forChannel(EndpointType::SMS))->toBe('short sms')
            ->and($payload->forChannel(EndpointType::DISCORD))->toBe('**discord**')
            ->and($payload->forChannel(EndpointType::EMAIL))->toBe('full body')
            ->and($payload->forChannel(EndpointType::TEAMS))->toBe('full body');
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
            ->and($payload->forChannel(EndpointType::SMS))->toBe('hello-world')
            ->and($payload->forChannel(EndpointType::TELEGRAM))->toBe('hello-world');
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
            ->and($payload->forChannel(EndpointType::SMS))->toBe('CPU High|critical|3|worker-7')
            ->and($payload->forChannel(EndpointType::TEAMS))->toBe('CPU High|critical|3|worker-7');
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

        expect($payload->forChannel(EndpointType::SMS))->toBe('N');
    });

    it('uses template text for telegram when the source has no telegram content', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'RuleA',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('ignored-for-telegram-channel');

        $payload = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'TG:{{name}}');

        expect($payload->forChannel(EndpointType::TELEGRAM))->toBe('TG:RuleA');
    });

    it('preserves telegram inline keyboard meta when applying template', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'GrafanaLike',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new TelegramInlineKeyboardMessageable('old-body');

        $payload = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'Firing: {{name}}');

        $telegram = $payload->forChannel(EndpointType::TELEGRAM);
        $bale = $payload->forChannel(EndpointType::BALE);

        expect($telegram)->toBeArray()
            ->and($telegram['message'])->toBe('Firing: GrafanaLike')
            ->and($telegram)->toHaveKey('meta')
            ->and($telegram['meta'][0]['text'] ?? null)->toBe('Acknowledge')
            ->and($telegram['meta'][0]['url'] ?? null)->toBe('https://example.test/ack/1')
            ->and($bale)->toBeArray()
            ->and($bale['message'])->toBe('Firing: GrafanaLike')
            ->and($bale['meta'][0]['text'] ?? null)->toBe('Acknowledge')
            ->and($bale['meta'][0]['url'] ?? null)->toBe('https://example.test/ack/1');
    });

    it('lets the template win over plain channel text', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'RuleB',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new class implements Messageable
        {
            use ProvidesChannelMessages;

            public function defaultMessage(): string
            {
                return 'body';
            }

            public function messageFor(EndpointType $type): array|string|null
            {
                return $type === EndpointType::CALL ? 'short call' : null;
            }
        };

        $payload = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'T:{{name}}');

        expect($payload->forChannel(EndpointType::CALL))->toBe('T:RuleB');
    });

    it('captures telegram and bale overrides from messageable alerts', function () {
        $alert = new TelegramInlineKeyboardMessageable('old-body');

        $payload = NotifyMessagePayload::fromMessageable($alert);

        expect($payload->defaultMessage())->toBe('default')
            ->and($payload->forChannel(EndpointType::TELEGRAM))->toBeArray()
            ->and($payload->forChannel(EndpointType::BALE))->toBeArray()
            ->and($payload->forChannel(EndpointType::CALL))->toBe('default');
    });
});
