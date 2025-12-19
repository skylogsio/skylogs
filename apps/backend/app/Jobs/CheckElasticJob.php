<?php

namespace App\Jobs;

use App\Models\AlertRule;
use App\Models\ElasticCheck;
use App\Models\ElasticHistory;
use App\Services\ElasticService;
use App\Services\SendNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckElasticJob implements ShouldQueue
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
        $check = ElasticCheck::firstOrCreate(
            [
                'alertRuleId' => $this->alert->_id,
            ],
            [
                'dataviewName' => $this->alert->dataviewName,
                'dataviewTitle' => $this->alert->dataviewTitle,
                'queryString' => $this->alert->queryString,
                'minutes' => $this->alert->minutes,
                'countDocument' => $this->alert->countDocument,
                'state' => ElasticCheck::RESOLVED,
            ]
        );

        $documents = ElasticService::getDocuments($check);
        $check->refresh();
        if (empty($this->alert->conditionType) || $this->alert->conditionType == ElasticCheck::CONDITION_TYPE_GREATER_OR_EQUAL) {
            $isFired = count($documents) >= $check->countDocument;
        } else {
            $isFired = count($documents) <= $check->countDocument;
        }

        if ($isFired) {
            if ($check->state != ElasticCheck::FIRE) {
                $check->state = ElasticCheck::FIRE;

                $alertRule = $check->alertRule;
                $alertRule->notifyAt = time();
                $alertRule->state = AlertRule::CRITICAL;
                $alertRule->save();

                $check->save();

                ElasticHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'dataviewName' => $this->alert->dataviewName,
                    'dataviewTitle' => $this->alert->dataviewTitle,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => count($documents),
                    'state' => ElasticCheck::FIRE,
                ]);

                SendNotifyService::CreateNotify(SendNotifyJob::ELASTIC_CHECK, $check, $this->alert->_id);
            }

        } else {
            if ($check->state != ElasticCheck::RESOLVED) {
                $check->state = ElasticCheck::RESOLVED;
                $alertRule = $check->alertRule;
                $alertRule->notifyAt = time();
                $alertRule->state = AlertRule::RESOlVED;
                $alertRule->save();
                $alertRule->removeAcknowledge();

                $check->save();

                ElasticHistory::create([
                    'alertRuleId' => $this->alert->_id,
                    'alertRuleName' => $this->alert->name,
                    'dataSourceId' => $this->alert->dataSourceId,
                    'dataviewName' => $this->alert->dataviewName,
                    'dataviewTitle' => $this->alert->dataviewTitle,
                    'queryString' => $this->alert->queryString,
                    'minutes' => $this->alert->minutes,
                    'countDocument' => $this->alert->countDocument,
                    'currentCountDocument' => count($documents),
                    'state' => ElasticCheck::RESOLVED,
                ]);

                SendNotifyService::CreateNotify(SendNotifyJob::ELASTIC_CHECK, $check, $this->alert->_id);

            }
        }

    }
}
