<?php

namespace Tests\Support\Factories;

use App\Models\AlertRule;

final class AlertRuleFactory
{
    /**
     * In-memory alert rule (no persistence), with model events disabled.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function unsaved(array $attributes): AlertRule
    {
        return AlertRule::withoutEvents(function () use ($attributes) {
            $model = new AlertRule;
            foreach ($attributes as $key => $value) {
                $model->setAttribute($key, $value);
            }

            return $model;
        });
    }
}
