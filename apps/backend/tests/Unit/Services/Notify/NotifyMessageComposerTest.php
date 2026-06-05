<?php

use App\Models\AlertRule;
use App\Services\NotifyMessageComposer;
use Tests\Support\Factories\AlertRuleFactory;
use Tests\Support\Messageables\PlainTextMessageable;
use Tests\Support\Messageables\StructuredPayloadMessageable;
use Tests\Support\Messageables\TelegramInlineKeyboardMessageable;

describe('NotifyMessageComposer', function () {
    it('maps all channel keys from fromMessageable', function () {
        $alert = new PlainTextMessageable('hello-world');

        $messages = NotifyMessageComposer::fromMessageable($alert);

        expect($messages)->toBe([
            'matterMostMessage' => 'hello-world',
            'telegram' => 'hello-world',
            'teamsMessage' => 'hello-world',
            'emailMessage' => 'hello-world',
            'smsMessage' => 'hello-world',
            'discordMessage' => 'hello-world',
            'callMessage' => 'hello-world',
            'defaultMessage' => 'hello-world',
        ]);
    });

    it('delegates buildMessages to fromMessageable when alert rule is null', function () {
        $alert = new PlainTextMessageable('no-rule');

        $messages = NotifyMessageComposer::buildMessages(null, $alert);

        expect($messages['smsMessage'])->toBe('no-rule')
            ->and($messages['defaultMessage'])->toBe('no-rule');
    });

    it('delegates buildMessages to fromMessageable for alert rules', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'R1',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('fallback');

        $messages = NotifyMessageComposer::buildMessages($rule, $alert);

        expect($messages['smsMessage'])->toBe('fallback');
    });

    it('applies one template string to every channel message key', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'CPU High',
            'state' => AlertRule::CRITICAL,
            'fireCount' => 3,
        ]);
        $alert = new StructuredPayloadMessageable('worker-7');

        $messages = NotifyMessageComposer::composeFromSingleTemplate(
            $rule,
            $alert,
            '{{name}}|{{state}}|{{fireCount}}|{{alert.instance}}',
        );

        expect($messages['smsMessage'])->toBe('CPU High|critical|3|worker-7')
            ->and($messages['teamsMessage'])->toBe('CPU High|critical|3|worker-7')
            ->and($messages['emailMessage'])->toBe('CPU High|critical|3|worker-7')
            ->and($messages['defaultMessage'])->toBe('CPU High|critical|3|worker-7');
    });

    it('renders unknown placeholders as empty', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'N',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('x');

        $messages = NotifyMessageComposer::composeFromSingleTemplate(
            $rule,
            $alert,
            '{{name}}{{not_a_real_key}}',
        );

        expect($messages['smsMessage'])->toBe('N');
    });

    it('applies template text to telegram when telegram() returns a string', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'RuleA',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new PlainTextMessageable('ignored-for-telegram-channel');

        $messages = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'TG:{{name}}');

        expect($messages['telegram'])->toBe('TG:RuleA');
    });

    it('preserves telegram inline keyboard meta when applying template', function () {
        $rule = AlertRuleFactory::unsaved([
            'name' => 'GrafanaLike',
            'state' => AlertRule::CRITICAL,
        ]);
        $alert = new TelegramInlineKeyboardMessageable('old-body');

        $messages = NotifyMessageComposer::composeFromSingleTemplate($rule, $alert, 'Firing: {{name}}');

        expect($messages['telegram'])->toBeArray()
            ->and($messages['telegram']['message'])->toBe('Firing: GrafanaLike')
            ->and($messages['telegram'])->toHaveKey('meta')
            ->and($messages['telegram']['meta'][0]['text'] ?? null)->toBe('Acknowledge')
            ->and($messages['telegram']['meta'][0]['url'] ?? null)->toBe('https://example.test/ack/1');
    });
});
