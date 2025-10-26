<?php

namespace App\Models;

use App\Interfaces\Messageable;
use Morilog\Jalali\Jalalian;

class ZabbixWebhookAlert extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'Zabbix Alert Webhook';

    public static $KEY = 'zabbix_alert_webhook';

    protected $guarded = ['id', '_id'];

    public const RESOLVED = 'RESOLVED';

    public const PROBLEM = 'PROBLEM';

    public function alertRule()
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function defaultMessage(): string
    {
        $text = $this->alertRuleName."\n\n";

        if (! empty($this->event_status)) {
            switch ($this->event_status) {
                case self::RESOLVED:
                    $text .= 'State: Resolved ✅'."\n\n";
                    break;
                case self::PROBLEM:
                    $text .= 'State: Fire 🔥'."\n\n";
                    break;
            }
        }

        $text .= "\nDataSource: ".$this->dataSourceName;
        $text .= "\n\n".$this->alert_subject;

        $text .= "\n\n".$this->alert_message;

        $text .= "\n\nSeverity: ";
        $text .= match ($this->event_severity) {
            'Not classified' => 'Not classified',
            'Information' => 'Information ℹ️',
            'Warning' => 'Warning ⚠️',
            'Average' => 'Average 🟠',
            'High' => 'High ⚡',
            'Disaster' => 'Disaster 🔥',
            default => $this->event_severity,
        };

        $text .= "\nDate: ".Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram()
    {
        $result = [
            'message' => $this->defaultMessage(),
        ];
        if ($this->alertRule->enableAcknowledgeBtnInMessage() && $this->event_status == self::PROBLEM) {
            $result['meta'] = [
                [
                    'text' => 'Acknowledge',
                    'url' => config('app.url').route('acknowledgeLink', ['id' => $this->alertRuleId], false),
                ],
            ];
        }

        return $result;
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

    public function discordMessage(): string
    {
        return $this->defaultMessage();
    }

    public function callMessage(): string
    {
        return $this->defaultMessage();
    }
}
