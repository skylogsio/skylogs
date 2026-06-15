<?php

namespace App\Services\AlertMessage;

use App\Enums\AlertRuleType;
use App\Interfaces\Messageable;
use App\Models\AlertRule;

final class AlertMessageTemplateRenderer
{
    public function __construct(
        private readonly PrometheusAlertMessageBuilder $prometheusBuilder = new PrometheusAlertMessageBuilder,
        private readonly GrafanaAlertMessageBuilder $grafanaBuilder = new GrafanaAlertMessageBuilder,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    public function render(AlertRule $rule, Messageable $source, string $template): string
    {
        return $this->renderFromPayload($rule, LegacyAlertMessageRenderer::resolvePayload($source), $template);
    }

    public function renderFromPayload(AlertRule $rule, array $payload, string $template): string
    {
        return match ($rule->type) {
            AlertRuleType::PROMETHEUS => $this->prometheusBuilder->render($rule, $payload, $template),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => $this->grafanaBuilder->render($rule, $payload, $template),
            default => LegacyAlertMessageRenderer::render($rule, new LegacyPayloadMessageable($payload), $template),
        };
    }

    public function renderDefault(AlertRule $rule, array $payload): string
    {
        return match ($rule->type) {
            AlertRuleType::PROMETHEUS => $this->prometheusBuilder->renderDefault($rule, $payload),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => $this->grafanaBuilder->renderDefault($rule, $payload),
            default => '',
        };
    }
}
