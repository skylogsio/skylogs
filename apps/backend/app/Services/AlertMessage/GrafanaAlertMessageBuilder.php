<?php

namespace App\Services\AlertMessage;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\AlertMessage\Concerns\RendersAlertMessageTemplate;

final class GrafanaAlertMessageBuilder
{
    use RendersAlertMessageTemplate;

    public const DEFAULT_TEMPLATE = <<<'TXT'
{{name}}

{{state_line}}
Data Source: {{dataSourceName}}

{{alert_items labels="*" annotations="summary,description"}}
{{date}}
TXT;

    public function render(AlertRule $rule, array $payload, string $template): string
    {
        $template = $this->expandBlockPlaceholders($template, $rule, $payload, AlertRuleType::GRAFANA);

        return $this->replaceSimplePlaceholders($template, $rule, $payload, AlertRuleType::GRAFANA, capitalizedDate: true);
    }

    public function renderDefault(AlertRule $rule, array $payload): string
    {
        return $this->render($rule, $payload, self::DEFAULT_TEMPLATE);
    }

    private function renderAlertItems(string $attributes, array $payload, AlertRuleType $type): string
    {
        $config = $this->parseAlertItemsAttributes($attributes);
        $sections = [];

        foreach ($payload['alerts'] ?? [] as $alert) {
            if (! is_array($alert)) {
                continue;
            }

            $lines = [AlertMessageFormatting::severityLine($alert, $type)];

            $labels = is_array($alert['labels'] ?? null) ? $alert['labels'] : [];
            $labelBlock = AlertMessageFormatting::formatKeyValueBlock($labels, $config['labels']);
            if ($labelBlock !== '') {
                $lines[] = $labelBlock;
            }

            $annotations = is_array($alert['annotations'] ?? null) ? $alert['annotations'] : [];
            $annotationBlock = AlertMessageFormatting::formatKeyValueBlock($annotations, $config['annotations']);
            if ($annotationBlock !== '') {
                $lines[] = $annotationBlock;
            }

            $sections[] = implode("\n", $lines)."\n\n************\n\n";
        }

        return implode('', $sections);
    }
}
