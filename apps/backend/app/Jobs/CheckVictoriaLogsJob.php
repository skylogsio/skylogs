<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\VictoriaLogsCheck;
use App\Models\VictoriaLogsHistory;
use App\Services\SendNotifyService;
use App\Services\VictoriaLogsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckVictoriaLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $alert;

    public function __construct($alert)
    {
        $this->onQueue('httpRequests');
        $this->alert = $alert;
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

                VictoriaLogsHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => $countDocuments,
                    'state' => VictoriaLogsCheck::FIRE,
                ]);

                SendNotifyService::CreateNotify(SendNotifyJob::VICTORIA_LOGS_CHECK, $check, $this->alert->_id);
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

                VictoriaLogsHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => $countDocuments,
                    'state' => VictoriaLogsCheck::RESOLVED,
                ]);

                SendNotifyService::CreateNotify(SendNotifyJob::VICTORIA_LOGS_CHECK, $check, $this->alert->_id);

            }
        }

    }
}
