<?php

namespace App\Models;

use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class GrafanaWebhookAlert extends BaseModel implements Messageable
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const FIRING = 'firing';

    public const RESOLVED = 'resolved';

    public function customSave($array)
    {
        try {

            foreach ($array as $item => $value) {
                $this->$item = $value;
            }

            $alert = $this->alertRule;

            if ($alert) {
                if ($this->status == self::RESOLVED) {
                    $alert->state = AlertRule::RESOlVED;
                } elseif ($this->status == self::FIRING) {
                    $alert->state = AlertRule::CRITICAL;
                }
                $alert->save();
            }
        } catch (\Exception $e) {

        }

        return $this->save();
    }

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function defaultMessage(): string
    {

        $needLabelAnotArray = ['summary', 'description'];

        $alertRule = $this->alertRule;

        $text = $alertRule->name."\n\n";

        switch ($this->status) {
            case GrafanaWebhookAlert::RESOLVED:
                $text .= 'State: Resolved ✅'."\n\n";
                break;
            case GrafanaWebhookAlert::FIRING:
                $text .= 'State: Firing 🔥'."\n\n";
                break;
        }

        $text .= 'Data Source: '.$this->dataSourceName."\n\n";

        if (! empty($this->alerts)) {
            foreach ($this->alerts as $alert) {
                //                $text .= "Grafana Instance: " . $alert['dataSourceName'] . "\n";
                if (empty($alert['status']) || $alert['status'] == self::FIRING) {
                    $severity = $alert['labels']['severity'] ?? '';
                    switch ($severity) {
                        case 'warning':
                            $text .= 'Warning ⚠️'."\n";
                            break;
                        case 'info':
                            $text .= 'Info ℹ️'."\n";
                            break;
                        default:
                            $text .= 'Fire 🔥'."\n";
                            break;
                    }
                } else {
                    $text .= 'Resolved ✅'."\n";

                }

                if (! empty($alert['labels'])) {
                    foreach ($alert['labels'] as $label => $labelValue) {
                        $text .= "$label : $labelValue\n";
                    }
                }

                if (! empty($alert['annotations'])) {
                    foreach ($needLabelAnotArray as $label) {
                        if (! empty($alert['annotations'][$label])) {
                            $text .= "$label : ".$alert['annotations'][$label]."\n";
                        }
                    }
                }
                $text .= "\n************\n\n";
            }
        }

        $text .= 'Date: '.Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram()
    {
        $result = [
            'message' => $this->defaultMessage(),
        ];
        if ($this->alertRule->enableAcknowledgeBtnInMessage() && $this->status == self::FIRING) {
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
        $alert = $this->alertRule;

        $text = 'Alert '.$alert->name;

        $text .= match ($this->status) {
            GrafanaWebhookAlert::FIRING => ' fired',
            GrafanaWebhookAlert::RESOLVED => ' resolved',
            default => '',
        };

        return $text;
    }

    public function teamsMessage(): string
    {
        return $this->defaultMessage();
    }

    public function emailMessage(): string
    {
        return $this->defaultMessage();
    }
}
