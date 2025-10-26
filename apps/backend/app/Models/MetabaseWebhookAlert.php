<?php

namespace App\Models;

use App\Helpers\Constants;
use App\Interfaces\Messageable;
use Morilog\Jalali\Jalalian;

class MetabaseWebhookAlert extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'Metabase Alert Webhook';

    public static $KEY = 'metabase_alert_webhook';

    protected $guarded = ['id', '_id'];

    public function alertRule()
    {
        return AlertRule::where('type', Constants::METABASE)->where('alertname', $this->alert_name)->first();
    }

    public function CustomSave($jsonWebhook)
    {
        try {
            $dataArray = $jsonWebhook['data'];
            $this->data = $dataArray;

            $this->type = $jsonWebhook['type'];
            $this->alert_id = $jsonWebhook['alert_id'];
            $this->alert_creator_id = $jsonWebhook['alert_creator_id'];
            $this->alert_creator_name = $jsonWebhook['alert_creator_name'];
            $this->question_name = $dataArray['question_name'];
            $this->alert_name = $dataArray['question_name'];
            $this->question_url = $dataArray['question_url'];

            $alert = AlertRule::where('alertname', $this->alert_name)->first();
            if ($alert) {
                $this->alertRuleId = $alert->_id;
                $alert->state = AlertRule::UNKNOWN;
                $alert->notifyAt = time();
                $alert->save();
            }

        } catch (\Exception $e) {

        }

        return $this->save();
    }

    public function defaultMessage(): string
    {
        $text = $this->alert_name;

        $text .= "\n⚠️ TRIGGERED Metabase Alert ";

        $text .= "\nUrl: ".$this->question_url;
        $text .= "\nDate: ".Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram()
    {
        return $this->defaultMessage();
    }

    public function teamsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function matterMostMessage(): string
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

    public function discordMessage(): string
    {
        return $this->defaultMessage();
    }

    public function callMessage(): string
    {
        return $this->defaultMessage();
    }
}
