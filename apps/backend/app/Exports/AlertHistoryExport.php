<?php

namespace App\Exports;

use App\Enums\AlertRuleType;
use App\Helpers\Utilities;
use App\Models\AlertRule;
use App\Models\ApiAlertHistory;
use App\Models\ElasticHistory;
use App\Models\HealthHistory;
use App\Models\PrometheusHistory;
use App\Models\VictoriaLogsHistory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlertHistoryExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, mixed>  $rows
     */
    public function __construct(
        private readonly AlertRule $alertRule,
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return match ($this->alertRule->type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => [
                'Alert Rule',
                'Instance',
                'Status',
                'Description',
                'Summary',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::PROMETHEUS => [
                'Alert Rule',
                'Status',
                'Instance',
                'Alert Name',
                'Severity',
                'Fire Count',
                'Resolve Count',
                'Summary',
                'Description',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::GRAFANA, AlertRuleType::PMM => [
                'Alert Rule',
                'Status',
                'Instance',
                'Alert Name',
                'Severity',
                'Title',
                'Message',
                'Summary',
                'Description',
                'Data Source',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::SENTRY => [
                'Alert Rule',
                'Action',
                'Title',
                'Message',
                'Description',
                'URL',
                'Project',
                'Data Source',
                'Data Source Alert Name',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::ZABBIX => [
                'Alert Rule',
                'Event Status',
                'Event Severity',
                'Host',
                'Event ID',
                'Subject',
                'Message',
                'Data Source',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::ELASTIC => [
                'Alert Rule',
                'Status',
                'Query',
                'Dataview',
                'Condition',
                'Minutes',
                'Threshold Count',
                'Current Count',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::VICTORIA_LOGS => [
                'Alert Rule',
                'Status',
                'Query',
                'Condition',
                'Minutes',
                'Threshold Count',
                'Current Count',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::HEALTH => [
                'Alert Rule',
                'Status',
                'URL',
                'Check Type',
                'Counter',
                'Threshold',
                ...$this->timestampHeadings(),
            ],
            AlertRuleType::METABASE => [
                'Alert Rule',
                'Status',
                'Question Name',
                'Alert Name',
                'Question URL',
                'Creator',
                'Type',
                ...$this->timestampHeadings(),
            ],
            default => [
                'Alert Rule',
                ...$this->timestampHeadings(),
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function map($history): array
    {
        return match ($this->alertRule->type) {
            AlertRuleType::API, AlertRuleType::NOTIFICATION => $this->mapApi($history),
            AlertRuleType::PROMETHEUS => $this->mapPrometheus($history),
            AlertRuleType::GRAFANA, AlertRuleType::PMM => $this->mapGrafana($history),
            AlertRuleType::SENTRY => $this->mapSentry($history),
            AlertRuleType::ZABBIX => $this->mapZabbix($history),
            AlertRuleType::ELASTIC => $this->mapElastic($history),
            AlertRuleType::VICTORIA_LOGS => $this->mapVictoriaLogs($history),
            AlertRuleType::HEALTH => $this->mapHealth($history),
            AlertRuleType::METABASE => $this->mapMetabase($history),
            default => [$this->alertName($history), ...$this->timestamps($history)],
        };
    }

    /**
     * @return list<string>
     */
    private function mapApi(mixed $history): array
    {
        return [
            $this->alertName($history),
            (string) ($history->instance ?? ''),
            (string) ($history->status ?? $this->fireResolveStatus($history->state ?? null)),
            (string) ($history->description ?? ''),
            (string) ($history->summary ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapPrometheus(mixed $history): array
    {
        $alert = $this->firstAlert($history->alerts ?? []);

        return [
            $this->alertName($history),
            $this->fireResolveStatus($history->state ?? null),
            $alert['instance'],
            $alert['alertname'],
            $alert['severity'],
            (string) (int) ($history->countFire ?? 0),
            (string) (int) ($history->countResolve ?? 0),
            $alert['summary'],
            $alert['description'],
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapGrafana(mixed $history): array
    {
        $alert = $this->firstAlert($history->alerts ?? []);

        return [
            $this->alertName($history),
            (string) ($history->status ?? ''),
            $alert['instance'],
            $alert['alertname'],
            $alert['severity'],
            (string) ($history->title ?? ''),
            (string) ($history->message ?? ''),
            $alert['summary'],
            $alert['description'],
            (string) ($history->dataSourceName ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapSentry(mixed $history): array
    {
        return [
            $this->alertName($history),
            (string) ($history->action ?? ''),
            (string) ($history->title ?? ''),
            (string) ($history->message ?? ''),
            (string) ($history->description ?? ''),
            (string) ($history->url ?? ''),
            (string) ($history->project_name ?? ''),
            (string) ($history->dataSourceName ?? ''),
            (string) ($history->dataSourceAlertName ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapZabbix(mixed $history): array
    {
        return [
            $this->alertName($history),
            (string) ($history->event_status ?? ''),
            (string) ($history->event_severity ?? ''),
            (string) ($history->host_name ?? ''),
            (string) ($history->event_id ?? ''),
            (string) ($history->alert_subject ?? ''),
            (string) ($history->alert_message ?? ''),
            (string) ($history->dataSourceName ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapElastic(mixed $history): array
    {
        return [
            $this->alertName($history),
            $this->fireResolveStatus($history->state ?? null),
            (string) ($history->queryString ?? ''),
            (string) ($history->dataviewTitle ?? $history->dataviewName ?? ''),
            (string) ($history->conditionType ?? ''),
            (string) ($history->minutes ?? ''),
            (string) ($history->countDocument ?? ''),
            (string) ($history->currentCountDocument ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapVictoriaLogs(mixed $history): array
    {
        return [
            $this->alertName($history),
            $this->fireResolveStatus($history->state ?? null),
            (string) ($history->queryString ?? ''),
            (string) ($history->conditionType ?? ''),
            (string) ($history->minutes ?? ''),
            (string) ($history->countDocument ?? ''),
            (string) ($history->currentCountDocument ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapHealth(mixed $history): array
    {
        $status = match ((int) ($history->state ?? 0)) {
            HealthHistory::DOWN => 'down',
            HealthHistory::UP => 'up',
            default => AlertRule::UNKNOWN,
        };

        return [
            $this->alertName($history),
            $status,
            (string) ($history->url ?? ''),
            (string) ($history->checkType ?? ''),
            (string) ($history->counter ?? ''),
            (string) ($history->threshold ?? ''),
            ...$this->timestamps($history),
        ];
    }

    /**
     * @return list<string>
     */
    private function mapMetabase(mixed $history): array
    {
        return [
            $this->alertName($history),
            'triggered',
            (string) ($history->question_name ?? ''),
            (string) ($history->alert_name ?? ''),
            (string) ($history->question_url ?? ''),
            (string) ($history->alert_creator_name ?? ''),
            (string) ($history->type ?? ''),
            ...$this->timestamps($history),
        ];
    }

    private function alertName(mixed $history): string
    {
        return (string) ($history->alertRuleName ?? $this->alertRule->name);
    }

    /**
     * @return list<string>
     */
    private function timestampHeadings(): array
    {
        return ['Created At (UTC)', 'Created At (Jalali)'];
    }

    /**
     * @return list<string>
     */
    private function timestamps(mixed $history): array
    {
        return [
            $history->createdAt?->timezone('UTC')->toIso8601String() ?? '',
            $history->createdAt ? Utilities::ConvertUTCTimeTOJalali($history->createdAt) : '',
        ];
    }

    private function fireResolveStatus(mixed $state): string
    {
        return match ((int) $state) {
            ApiAlertHistory::FIRE, PrometheusHistory::FIRE, ElasticHistory::FIRE, VictoriaLogsHistory::FIRE => AlertRule::CRITICAL,
            ApiAlertHistory::RESOLVED, PrometheusHistory::RESOLVED, ElasticHistory::RESOLVED, VictoriaLogsHistory::RESOLVED => AlertRule::RESOlVED,
            default => AlertRule::UNKNOWN,
        };
    }

    /**
     * @return array{instance: string, alertname: string, severity: string, summary: string, description: string}
     */
    private function firstAlert(mixed $alerts): array
    {
        $first = is_array($alerts) && $alerts !== [] ? $alerts[0] : [];
        if (! is_array($first)) {
            $first = [];
        }

        $labels = is_array($first['labels'] ?? null) ? $first['labels'] : [];
        $annotations = is_array($first['annotations'] ?? null) ? $first['annotations'] : [];

        return [
            'instance' => (string) ($labels['instance'] ?? ''),
            'alertname' => (string) ($labels['alertname'] ?? ''),
            'severity' => (string) ($labels['severity'] ?? ''),
            'summary' => (string) ($annotations['summary'] ?? ''),
            'description' => (string) ($annotations['description'] ?? ''),
        ];
    }
}
