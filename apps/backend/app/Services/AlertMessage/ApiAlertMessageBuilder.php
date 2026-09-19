<?php

namespace App\Services\AlertMessage;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;
use Morilog\Jalali\Jalalian;
use Throwable;

final class ApiAlertMessageBuilder
{
    public const DEFAULT_TEMPLATE = <<<'TXT'
{{name}}
{{state_line}}
{{instance_line}}{{description_line}}{{date}}
TXT;

    public function render(AlertRule $rule, array $payload, string $template): string
    {
        $type = $rule->type instanceof AlertRuleType ? $rule->type : AlertRuleType::API;
        $replacements = [
            'name' => (string) ($rule->name ?: ($payload['alertRuleName'] ?? '')),
            'alertRuleName' => (string) ($rule->name ?: ($payload['alertRuleName'] ?? '')),
            'state' => AlertMessageFormatting::stateValue($rule, $payload, $type),
            'state_line' => AlertMessageFormatting::stateLine($rule, $payload, $type),
            'fireCount' => (string) ($rule->fireCount ?? ''),
            'instance' => $this->stringValue($payload['instance'] ?? ''),
            'description' => $this->stringValue($payload['description'] ?? ''),
            'summary' => $this->stringValue($payload['summary'] ?? ''),
            'job' => $this->stringValue($payload['job'] ?? ''),
            'instance_line' => $this->prefixedLine('Instance', $payload['instance'] ?? ''),
            'description_line' => $this->prefixedLine('Description', $payload['description'] ?? ''),
            'summary_line' => $this->prefixedLine('Summary', $payload['summary'] ?? ''),
            'date' => $this->formatDate($payload),
            'alert.instance' => $this->stringValue($payload['instance'] ?? ''),
            'alert.description' => $this->stringValue($payload['description'] ?? ''),
            'alert.summary' => $this->stringValue($payload['summary'] ?? ''),
            'alert.job' => $this->stringValue($payload['job'] ?? ''),
        ];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            fn (array $matches): string => $replacements[$matches[1]] ?? '',
            $template,
        );
    }

    public function renderDefault(AlertRule $rule, array $payload): string
    {
        return $this->render($rule, $payload, self::DEFAULT_TEMPLATE);
    }

    private function prefixedLine(string $label, mixed $value): string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $label.': '.$value."\n" : '';
    }

    private function stringValue(mixed $value): string
    {
        return trim((string) $value);
    }

    private function formatDate(array $payload): string
    {
        $carbon = $this->parseTimestamp($payload['updatedAt'] ?? null);
        $jalali = $carbon !== null
            ? Jalalian::fromCarbon($carbon)
            : Jalalian::now();

        return 'Date: '.$jalali->format('Y/m/d H:i:s');
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        if ($value instanceof UTCDateTime) {
            return Carbon::instance(Carbon::parse($value->toDateTime()));
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
