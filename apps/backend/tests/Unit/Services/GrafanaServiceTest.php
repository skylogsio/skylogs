<?php

use App\Models\GrafanaWebhookAlert;
use App\Services\GrafanaService;

/**
 * @param  array<string, mixed>  $labels
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function grafana_merge_test_alert(string $status, array $labels, array $overrides = []): array
{
    return array_merge([
        'dataSourceId' => 1,
        'alertRuleName' => 'TestRule',
        'dataSourceAlertName' => $labels['alertname'] ?? 'Alert',
        'labels' => $labels,
        'annotations' => [],
        'alertRuleId' => '507f1f77bcf86cd799439011',
        'status' => $status,
        'startsAt' => '2020-01-01T00:00:00Z',
        'endsAt' => '',
        'generatorURL' => 'http://grafana.example/alerting/grafana/uid/generator',
        'orgId' => 1,
    ], $overrides);
}

describe('GrafanaService instance keys', function () {
    it('uses fingerprint as primary instance key', function () {
        $alert = [
            'fingerprint' => 'fp-primary',
            'labels' => ['alertname' => 'CPU', 'instance' => 'host-a'],
            'generatorURL' => 'http://x',
            'startsAt' => '2020-01-01T00:00:00Z',
        ];

        expect(GrafanaService::grafanaAlertInstanceKey($alert))->toBe('fp-primary');
    });

    it('falls back to legacy key when fingerprint is absent', function () {
        $alert = [
            'labels' => ['alertname' => 'CPU', 'instance' => 'host-b'],
            'generatorURL' => 'http://gen',
            'startsAt' => '2020-02-02T12:00:00Z',
        ];

        expect(GrafanaService::grafanaAlertInstanceKey($alert))
            ->toBe(GrafanaService::legacyGrafanaAlertInstanceKey($alert))
            ->toStartWith('legacy:');
    });
});

describe('GrafanaService::mergeGrafanaCheckAlertBatch', function () {
    it('accumulates multiple firing series with the same alertname', function () {
        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'HighCPU',
                'instance' => 'srv-1',
            ], ['fingerprint' => 'fp-1']),
        ];

        $incoming = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'HighCPU',
                'instance' => 'srv-2',
            ], ['fingerprint' => 'fp-2']),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(2)
            ->and(collect($merged)->pluck('fingerprint')->sort()->values()->all())
            ->toBe(['fp-1', 'fp-2']);
    });

    it('does not drop other firing instances when the webhook is partial', function () {
        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'Mem',
                'instance' => 'a',
            ], ['fingerprint' => 'fp-a']),
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'Mem',
                'instance' => 'b',
            ], ['fingerprint' => 'fp-b']),
        ];

        $incoming = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'Mem',
                'instance' => 'c',
            ], ['fingerprint' => 'fp-c']),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(3)
            ->and(collect($merged)->pluck('labels.instance')->sort()->values()->all())
            ->toBe(['a', 'b', 'c']);
    });

    it('removes only the resolved fingerprint and leaves other series', function () {
        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'Disk',
                'instance' => 'n1',
            ], ['fingerprint' => 'fp-n1']),
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'Disk',
                'instance' => 'n2',
            ], ['fingerprint' => 'fp-n2']),
        ];

        $incoming = [
            grafana_merge_test_alert(GrafanaWebhookAlert::RESOLVED, [
                'alertname' => 'Disk',
                'instance' => 'n1',
            ], ['fingerprint' => 'fp-n1']),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(1)
            ->and($merged[0]['fingerprint'] ?? null)->toBe('fp-n2');
    });

    it('replaces legacy-only stored row when the same series arrives with a fingerprint', function () {
        $labels = ['alertname' => 'Net', 'instance' => 'gw-1'];

        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, $labels),
        ];

        $incoming = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, $labels, [
                'fingerprint' => 'fp-net-1',
            ]),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(1)
            ->and($merged[0]['fingerprint'])->toBe('fp-net-1');
    });

    it('clears legacy duplicate when resolved arrives with fingerprint', function () {
        $labels = ['alertname' => 'IO', 'instance' => 'db-1'];

        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, $labels),
        ];

        $incoming = [
            grafana_merge_test_alert(GrafanaWebhookAlert::RESOLVED, $labels, [
                'fingerprint' => 'fp-io-1',
            ]),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(0);
    });

    it('ignores unknown status values', function () {
        $stored = [
            grafana_merge_test_alert(GrafanaWebhookAlert::FIRING, [
                'alertname' => 'X',
                'instance' => '1',
            ], ['fingerprint' => 'fp-x']),
        ];

        $incoming = [
            grafana_merge_test_alert('pending', [
                'alertname' => 'X',
                'instance' => '2',
            ], ['fingerprint' => 'fp-y']),
        ];

        $merged = GrafanaService::mergeGrafanaCheckAlertBatch($stored, $incoming);

        expect($merged)->toHaveCount(1)
            ->and($merged[0]['fingerprint'])->toBe('fp-x');
    });
});
