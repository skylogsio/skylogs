<?php

namespace App\Services\AlertMessage;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Services\AlertMessage\Concerns\RendersAlertMessageTemplate;

final class PrometheusAlertMessageBuilder
{
    use RendersAlertMessageTemplate;

    public const FULL_TEMPLATE = <<<'TXT'
{{name}}

{{state_line}}
{{alert_items labels="*" annotations="*"}}
{{date}}
TXT;

    public const DEFAULT_TEMPLATE = <<<'TXT'
{{name}}

{{state_line}}
{{alert_items labels="*" annotations="*" changed_only="true"}}
{{other_firing_line}}
{{date}}
TXT;

    public function render(AlertRule $rule, array $payload, string $template): string
    {
        $template = $this->expandBlockPlaceholders($template, $rule, $payload, AlertRuleType::PROMETHEUS);

        return $this->replaceSimplePlaceholders($template, $rule, $payload, AlertRuleType::PROMETHEUS);
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

            if ($config['changedOnly'] && array_key_exists('skylogsChanged', $alert) && $alert['skylogsChanged'] === false) {
                continue;
            }

            $lines = [AlertMessageFormatting::severityLine($alert, $type)];

            if ($config['showDataSource'] && ! empty($alert['dataSourceName'])) {
                $lines[] = 'Data Source: '.$alert['dataSourceName'];
            }

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
