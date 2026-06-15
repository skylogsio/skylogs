<?php

namespace App\Services\AlertMessage;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\GrafanaWebhookAlert;
use App\Models\PrometheusCheck;
use Morilog\Jalali\Jalalian;

final class AlertMessageFormatting
{
    public static function formatDate(bool $capitalized = false): string
    {
        $prefix = $capitalized ? 'Date' : 'date';

        return $prefix.': '.Jalalian::now()->format('Y/m/d');
    }

    public static function stateLine(AlertRule $rule, array $payload, AlertRuleType $type): string
    {
        return match ($type) {
            AlertRuleType::PROMETHEUS => self::prometheusStateLine($payload),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => self::grafanaStateLine($payload),
            default => self::genericStateLine($rule),
        };
    }

    public static function stateValue(AlertRule $rule, array $payload, AlertRuleType $type): string
    {
        return match ($type) {
            AlertRuleType::PROMETHEUS => match ((int) ($payload['state'] ?? 0)) {
                PrometheusCheck::RESOLVED => AlertRule::RESOlVED,
                PrometheusCheck::FIRE => AlertRule::CRITICAL,
                default => (string) ($rule->state ?? ''),
            },
            AlertRuleType::GRAFANA, AlertRuleType::PMM => match ($payload['status'] ?? '') {
                GrafanaWebhookAlert::RESOLVED => AlertRule::RESOlVED,
                GrafanaWebhookAlert::FIRING => AlertRule::CRITICAL,
                default => (string) ($rule->state ?? ''),
            },
            default => (string) ($rule->state ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    public static function severityLine(array $alert, AlertRuleType $type): string
    {
        $isFiring = match ($type) {
            AlertRuleType::PROMETHEUS => empty($alert['skylogsStatus'])
                || (int) $alert['skylogsStatus'] === PrometheusCheck::FIRE,
            AlertRuleType::GRAFANA, AlertRuleType::PMM => empty($alert['status'])
                || $alert['status'] === GrafanaWebhookAlert::FIRING,
            default => true,
        };

        if (! $isFiring) {
            return 'Resolved ✅';
        }

        $severity = $alert['labels']['severity'] ?? '';

        return match ($severity) {
            'warning' => 'Warning ⚠️',
            'info' => 'Info ℹ️',
            default => 'Fire 🔥',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function formatKeyValueBlock(array $data, LabelFilter $filter): string
    {
        $lines = [];

        foreach ($filter->keys($data) as $key) {
            $lines[] = $key.' : '.$data[$key];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function firstFiringAlert(array $payload, AlertRuleType $type): ?array
    {
        foreach ($payload['alerts'] ?? [] as $alert) {
            if (! is_array($alert)) {
                continue;
            }

            if (self::isFiringAlert($alert, $type)) {
                return $alert;
            }
        }

        $first = $payload['alerts'][0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    public static function isFiringAlert(array $alert, AlertRuleType $type): bool
    {
        return match ($type) {
            AlertRuleType::PROMETHEUS => empty($alert['skylogsStatus'])
                || (int) $alert['skylogsStatus'] === PrometheusCheck::FIRE,
            AlertRuleType::GRAFANA, AlertRuleType::PMM => empty($alert['status'])
                || $alert['status'] === GrafanaWebhookAlert::FIRING,
            default => true,
        };
    }

    private static function prometheusStateLine(array $payload): string
    {
        $line = match ((int) ($payload['state'] ?? 0)) {
            PrometheusCheck::RESOLVED => 'State: Resolved ✅',
            PrometheusCheck::FIRE => 'State: Fire 🔥',
            default => '',
        };

        return $line !== '' ? $line."\n\n" : '';
    }

    private static function grafanaStateLine(array $payload): string
    {
        $line = match ($payload['status'] ?? '') {
            GrafanaWebhookAlert::RESOLVED => 'State: Resolved ✅',
            GrafanaWebhookAlert::FIRING => 'State: Firing 🔥',
            default => '',
        };

        return $line !== '' ? $line."\n\n" : '';
    }

    private static function genericStateLine(AlertRule $rule): string
    {
        $line = match ($rule->state) {
            AlertRule::RESOlVED => 'State: Resolved ✅',
            AlertRule::CRITICAL => 'State: Fire 🔥',
            AlertRule::WARNING => 'State: Warning ⚠️',
            default => '',
        };

        return $line !== '' ? $line."\n\n" : '';
    }
}
