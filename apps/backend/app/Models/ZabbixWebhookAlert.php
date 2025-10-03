<?php

namespace App\Models;

use App\Helpers\Constants;
use App\Interfaces\Messageable;
use Morilog\Jalali\Jalalian;

class ZabbixWebhookAlert extends BaseModel implements Messageable
{

    public $timestamps = true;
    public static $title = "Zabbix Alert Webhook";
    public static $KEY = "zabbix_alert_webhook";

    protected $guarded = ['id', '_id',];

    public function alertRule()
    {
        return AlertRule::where("id", $this->alertRuleId)->first();
    }

    public function defaultMessage(): string
    {
        $text = $this->alertRuleName;

        $text .= "\nDataSource: ".$this->dataSourceName;
        $text .= "\n\n" . $this->alert_subject;

        $text .= "\n\n" . $this->alert_message;

        if (!empty($this->event_nseverity) && in_array($this->event_nseverity, ["0", "1", "2", "3", "4", "5",])) {

            $text .= "\n\nSeverity: ";
            $text .= match ($this->event_nseverity) {
                "0" => "Not classified",
                "1" => "Information ℹ️",
                "2" => "Warning ⚠️",
                "3" => "Average 🟠",
                "4" => "High ⚡",
                "5" => "Disaster 🔥"
            };
        }
        $text .= "\nDate: " . Jalalian::now()->format("Y/m/d");

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
