<?php

namespace App\Collectors\Prometheus;

use App\Services\Notification\NotificationMetricsRecorder;
use Spatie\Prometheus\Collectors\Collector;
use Spatie\Prometheus\Facades\Prometheus;

class NotificationProfilesCollector implements Collector
{
    public function register(): void
    {
        Prometheus::addCounter('Notification profile attempts')
            ->name('notification_profile_attempts_total')
            ->helpText('Profile attempts in the notification failover chain')
            ->labels(['node_id', 'endpoint_type', 'provider', 'priority', 'profile_id', 'outcome'])
            ->setInitialValue(fn (): array => app(NotificationMetricsRecorder::class)->attemptSamples());

        Prometheus::addCounter('Notification profile request duration seconds sum')
            ->name('notification_profile_request_duration_seconds_sum')
            ->helpText('Total seconds spent waiting on a notification profile')
            ->labels(['node_id', 'endpoint_type', 'provider', 'priority', 'profile_id'])
            ->setInitialValue(fn (): array => app(NotificationMetricsRecorder::class)->durationSumSamples());

        Prometheus::addCounter('Notification profile request duration seconds count')
            ->name('notification_profile_request_duration_seconds_count')
            ->helpText('Number of timed notification profile attempts')
            ->labels(['node_id', 'endpoint_type', 'provider', 'priority', 'profile_id'])
            ->setInitialValue(fn (): array => app(NotificationMetricsRecorder::class)->durationCountSamples());

        Prometheus::addCounter('Notification chain runs')
            ->name('notification_chain_runs_total')
            ->helpText('Notification failover chain runs by final outcome')
            ->labels(['node_id', 'endpoint_type', 'outcome'])
            ->setInitialValue(fn (): array => app(NotificationMetricsRecorder::class)->chainRunSamples());
    }
}
