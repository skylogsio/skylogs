<?php

namespace App\Models;

use App\Helpers\Utilities;
use App\Interfaces\Messageable;
use Morilog\Jalali\Jalalian;

class SentryWebhookAlert extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'Sentry Alert Webhook';

    public static $KEY = 'sentry_alert_webhook';

    protected $guarded = ['id', '_id'];

    public function alertRule()
    {
        return AlertRule::where('id', $this->alertRuleId)->first();
    }

    public function CustomSave($dataSource, $alertRules, $jsonWebhook)
    {
        try {
            $dataArray = $jsonWebhook['data'];
            Utilities::removeEmptyKeys($dataArray);
            $this->data = $dataArray;

            if (! empty($dataArray['metric_alert'])) {
                $this->project_name = $jsonWebhook['data']['metric_alert']['projects'][0] ?? '';
                $this->action = $jsonWebhook['action'];

                $this->message = $jsonWebhook['data']['description_text'] ?? '';
                $this->description = $jsonWebhook['data']['description_text'] ?? '';
                $this->url = $jsonWebhook['data']['web_url'] ?? '';
                $this->dataSourceAlertName = $jsonWebhook['data']['metric_alert']['alert_rule']['name'] ?? '';
                $this->dataSourceId = $dataSource->id;
                $this->dataSourceName = $dataSource->name;

                $alertRule = $alertRules->where('dataSourceAlertName', $this->dataSourceAlertName)->first();
                if ($alertRule) {
                    $this->alertRuleId = $alertRule->_id;
                    $this->alertRuleName = $alertRule->name;
                    $alertRule->state = $this->action;
                    $alertRule->notifyAt = time();
                    $alertRule->save();
                }

            } else {

                $this->action = $jsonWebhook['action'];

                $this->message = $jsonWebhook['data']['event']['message'] ?? '';
                $this->title = $jsonWebhook['data']['event']['title'] ?? '';
                $this->description = $jsonWebhook['data']['event']['message'] ?? '';
                $this->url = $jsonWebhook['data']['event']['web_url'] ?? '';
                $this->dataSourceAlertName = $jsonWebhook['data']['triggered_rule'] ?? '';
                $this->dataSourceId = $dataSource->id;
                $this->dataSourceName = $dataSource->name;
                $alertRule = $alertRules->where('dataSourceAlertName', $this->dataSourceAlertName)->first();

                if ($alertRule) {
                    $this->alertRuleId = $alertRule->_id;
                    $this->alertRuleName = $alertRule->name;

                    $alertRule->state = $this->action;
                    $alertRule->notifyAt = time();
                    $alertRule->save();
                    if ($alertRule->state == AlertRule::RESOlVED) {
                        $alertRule->removeAcknowledge();
                    }
                }
            }

        } catch (\Exception $e) {

        }

        return $this->save();
    }

    public function defaultMessage(): string
    {
        $text = $this->alertRuleName;

        if (! empty($this->action)) {
            switch ($this->action) {
                case AlertRule::RESOlVED:
                    $text .= "\nSeverity: Resolved ✅";
                    break;
                case AlertRule::WARNING:
                    $text .= "\nSeverity: Warning ⚠️";
                    break;
                case AlertRule::CRITICAL:
                    $text .= "\nSeverity: Critical 🔥";
                    break;
                case AlertRule::TRIGGERED:
                    $text .= "\nTRIGGERED Alert 📢";
                    break;

            }
        }

        $text .= "\n";
        $text .= "\nDataSource: ".$this->dataSourceName;
        if (! empty($this->title)) {
            $text .= "\nTitle: ".$this->title;
        }
        $text .= "\nDescription: ".$this->description;
        $text .= "\nUrl: ".$this->url;
        $text .= "\nDate: ".Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram()
    {
        return $this->defaultMessage();
    }

    public function matterMostMessage()
    {
        return $this->defaultMessage();
    }

    public function teamsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function emailMessage(): string
    {
        return $this->defaultMessage();
    }

    public function smsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function callMessage(): string
    {
        return $this->defaultMessage();
    }
}
