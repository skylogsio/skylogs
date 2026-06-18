<?php

namespace App\Services\AlertMessage\Concerns;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\AlertMessage\AlertMessageFormatting;
use App\Services\AlertMessage\LabelFilter;

trait RendersAlertMessageTemplate
{
    protected function expandBlockPlaceholders(string $template, AlertRule $rule, array $payload, AlertRuleType $type): string
    {
        $template = (string) preg_replace_callback(
            '/\{\{\s*alert_items\s+([^}]+)\s*\}\}/',
            fn (array $matches): string => $this->renderAlertItems($matches[1], $payload, $type),
            $template,
        );

        $template = (string) preg_replace_callback(
            '/\{\{\s*labels:([^}]+)\s*\}\}/',
            function (array $matches) use ($payload, $type): string {
                $alert = AlertMessageFormatting::firstFiringAlert($payload, $type);
                $labels = is_array($alert['labels'] ?? null) ? $alert['labels'] : [];

                return AlertMessageFormatting::formatKeyValueBlock(
                    $labels,
                    LabelFilter::fromDirective(trim($matches[1])),
                );
            },
            $template,
        );

        return (string) preg_replace_callback(
            '/\{\{\s*annotations:([^}]+)\s*\}\}/',
            function (array $matches) use ($payload, $type): string {
                $alert = AlertMessageFormatting::firstFiringAlert($payload, $type);
                $annotations = is_array($alert['annotations'] ?? null) ? $alert['annotations'] : [];

                return AlertMessageFormatting::formatKeyValueBlock(
                    $annotations,
                    LabelFilter::fromDirective(trim($matches[1])),
                );
            },
            $template,
        );
    }

    protected function replaceSimplePlaceholders(
        string $template,
        AlertRule $rule,
        array $payload,
        AlertRuleType $type,
        bool $capitalizedDate = false,
    ): string {
        $firstAlert = AlertMessageFormatting::firstFiringAlert($payload, $type);
        $replacements = [
            'name' => (string) ($rule->name ?? ''),
            'state' => AlertMessageFormatting::stateValue($rule, $payload, $type),
            'state_line' => AlertMessageFormatting::stateLine($rule, $payload, $type),
            'fireCount' => (string) ($rule->fireCount ?? ''),
            'date' => AlertMessageFormatting::formatDate($capitalizedDate),
            'dataSourceName' => $this->resolveDataSourceName($payload, $firstAlert),
            'severity_line' => $firstAlert !== null
                ? AlertMessageFormatting::severityLine($firstAlert, $type)
                : '',
        ];

        $template = (string) preg_replace_callback(
            '/\{\{\s*(label|annotation)\.([a-zA-Z0-9_.-]+)\s*\}\}/',
            function (array $matches) use ($firstAlert): string {
                if ($firstAlert === null) {
                    return '';
                }

                $bucket = $matches[1] === 'label' ? 'labels' : 'annotations';
                $key = $matches[2];
                $data = is_array($firstAlert[$bucket] ?? null) ? $firstAlert[$bucket] : [];

                return (string) ($data[$key] ?? '');
            },
            $template,
        );

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            fn (array $matches): string => $replacements[$matches[1]] ?? '',
            $template,
        );
    }

    /**
     * @param  array<string, mixed>|null  $firstAlert
     */
    protected function resolveDataSourceName(array $payload, ?array $firstAlert): string
    {
        if (! empty($payload['dataSourceName'])) {
            return (string) $payload['dataSourceName'];
        }

        if ($firstAlert !== null && ! empty($firstAlert['dataSourceName'])) {
            return (string) $firstAlert['dataSourceName'];
        }

        return '';
    }

    /**
     * @return array{labels: LabelFilter, annotations: LabelFilter, showDataSource: bool}
     */
    protected function parseAlertItemsAttributes(string $attributes): array
    {
        $labels = new LabelFilter;
        $annotations = new LabelFilter;
        $showDataSource = true;

        if (preg_match('/labels="([^"]*)"/', $attributes, $matches)) {
            $labels = LabelFilter::fromDirective($matches[1]);
        }

        if (preg_match('/exclude_labels="([^"]*)"/', $attributes, $matches)) {
            $labels = new LabelFilter(include: null, exclude: $this->splitAttributeKeys($matches[1]));
        }

        if (preg_match('/annotations="([^"]*)"/', $attributes, $matches)) {
            $annotations = LabelFilter::fromDirective($matches[1]);
        }

        if (preg_match('/exclude_annotations="([^"]*)"/', $attributes, $matches)) {
            $annotations = new LabelFilter(include: null, exclude: $this->splitAttributeKeys($matches[1]));
        }

        if (preg_match('/show_data_source="(true|false)"/', $attributes, $matches)) {
            $showDataSource = $matches[1] === 'true';
        }

        return [
            'labels' => $labels,
            'annotations' => $annotations,
            'showDataSource' => $showDataSource,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitAttributeKeys(string $value): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $value))));
    }
}
