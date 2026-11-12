<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Queue\Middleware\EnsureLeader;
use App\Services\SendNotifyService;
use App\Services\VictoriaLogsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckVictoriaLogsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 15;

    public int $uniqueFor;

    public $alert;

    public function __construct($alert)
    {
        $this->onQueue('logChecks');
        $this->alert = $alert;
        $this->uniqueFor = (int) config('datasource_checks.unique_for', 30);
    }

    public function uniqueId(): string
    {
        return (string) $this->alert->_id;
    }

    /**
     * Replaces Horizon's automatic model tag, so the alert rule tag is listed here.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return array_values(array_filter([
            'datasource-check',
            'victoria-logs',
            'alertRule:'.$this->alert->_id,
            empty($this->alert->dataSourceId) ? null : 'dataSource:'.$this->alert->dataSourceId,
        ]));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureLeader];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $check = VictoriaLogsCheck::firstOrCreate(
            [
                'alertRuleId' => $this->alert->_id,
            ],
            [
                'queryString' => $this->alert->queryString,
                'minutes' => $this->alert->minutes,
                'countDocument' => $this->alert->countDocument,
                'currentCountDocument' => 0,
                'state' => VictoriaLogsCheck::RESOLVED,
            ]
        );

        $countDocuments = VictoriaLogsService::countDocuments($check);

        if ($countDocuments === null) {
            return;
        }

        $check->refresh();
        if (empty($this->alert->conditionType) || $this->alert->conditionType == VictoriaLogsCheck::CONDITION_TYPE_GREATER_OR_EQUAL) {
            $isFired = $countDocuments >= $check->countDocument;
        } else {
            $isFired = $countDocuments <= $check->countDocument;
        }

        if ($isFired) {
            if ($check->state != VictoriaLogsCheck::FIRE) {
                $check->state = VictoriaLogsCheck::FIRE;

                $alertRule = $check->alertRule;
                $alertRule->notifyAt = time();
                $alertRule->state = AlertRule::CRITICAL;
                $alertRule->save();
                $check->currentCountDocument = $countDocuments;

                $check->save();

                $history = VictoriaLogsHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => $countDocuments,
                    'state' => VictoriaLogsCheck::FIRE,
                ]);

                app(SendNotifyService::class)->createNotify(SendNotifyJob::VICTORIA_LOGS_CHECK, $check, $this->alert->_id, $history);
            } elseif ($check->currentCountDocument !== $countDocuments) {
                $check->currentCountDocument = $countDocuments;
                $check->save();
            }

        } else {
            if ($check->state != VictoriaLogsCheck::RESOLVED) {
                $check->state = VictoriaLogsCheck::RESOLVED;
                $alertRule = $check->alertRule;
                $alertRule->notifyAt = time();
                $alertRule->state = AlertRule::RESOlVED;
                $alertRule->save();
                $alertRule->removeAcknowledge();
                $check->currentCountDocument = $countDocuments;

                $check->save();

                $history = VictoriaLogsHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => $countDocuments,
                    'state' => VictoriaLogsCheck::RESOLVED,
                ]);

                app(SendNotifyService::class)->createNotify(SendNotifyJob::VICTORIA_LOGS_CHECK, $check, $this->alert->_id, $history);

            }
        }

    }
}
