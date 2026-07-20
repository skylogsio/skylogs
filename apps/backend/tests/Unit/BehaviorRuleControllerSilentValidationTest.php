<?php

use App\Http\Controllers\V1\AlertRule\BehaviorRuleController;
use App\Models\AlertRule;
use Illuminate\Validation\ValidationException;

function invokeAssertValidSilentRulePayload(array $ruleData): void
{
    $controller = app(BehaviorRuleController::class);

    $method = (new ReflectionClass($controller))->getMethod('assertValidSilentRulePayload');
    $method->setAccessible(true);

    $method->invoke($controller, $ruleData);
}

describe('BehaviorRuleController silent payload validation', function () {
    it('requires triggerState when dependsOnAlertRuleIds has at least one item', function () {
        try {
            invokeAssertValidSilentRulePayload([
                'dependsOnAlertRuleIds' => ['dep-1'],
                'triggerState' => '',
            ]);

            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('triggerState');
        }
    });

    it('passes when dependsOnAlertRuleIds and triggerState are provided together', function () {
        invokeAssertValidSilentRulePayload([
            'dependsOnAlertRuleIds' => ['dep-1'],
            'triggerState' => AlertRule::RESOlVED,
        ]);

        expect(true)->toBeTrue();
    });

    it('allows an empty triggerState when dependsOnAlertRuleIds is empty', function () {
        invokeAssertValidSilentRulePayload([
            'dependsOnAlertRuleIds' => [],
            'triggerState' => '',
            'filters' => [['key' => 'instance', 'value' => 'api*']],
        ]);

        expect(true)->toBeTrue();
    });

    it('does not couple a provided triggerState to dependsOnAlertRuleIds', function () {
        invokeAssertValidSilentRulePayload([
            'dependsOnAlertRuleIds' => [],
            'triggerState' => AlertRule::CRITICAL,
            'filters' => [['key' => 'instance', 'value' => 'api*']],
        ]);

        expect(true)->toBeTrue();
    });

    it('still requires at least one silent condition', function () {
        try {
            invokeAssertValidSilentRulePayload([
                'dependsOnAlertRuleIds' => [],
                'triggerState' => '',
            ]);

            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('filters');
        }
    });
});
