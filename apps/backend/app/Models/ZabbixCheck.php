<?php

namespace App\Models;

use App\Interfaces\Messageable;
use App\Models\DataSource\DataSource;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class ZabbixCheck extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'Zabbix Check';

    public static $KEY = 'zabbix_check';

    protected $guarded = ['id', '_id'];

    public const RESOLVED = 'RESOLVED';

    public const PROBLEM = 'PROBLEM';

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'dataSourceId', '_id');
    }

    public function createHistory()
    {

        $countFire = count($this->fireEvents ?? []);

    }

    public function defaultMessage(): string
    {
        $needLabelArray = ['alertname', 'namespace', 'pod', 'reason', 'severity', 'job'];
        $needLabelAnotArray = ['summary', 'description'];

        $alertRule = $this->alertRule;

        $text = $alertRule->name."\n\n";
        if (! empty($this->state)) {
            switch ($this->state) {
                case self::RESOLVED:
                    $text .= 'State: Resolved ✅'."\n\n";
                    break;
                case self::FIRE:
                    $text .= 'State: Fire 🔥'."\n\n";
                    break;
            }
        }

        if (! empty($this->alerts)) {
            foreach ($this->alerts as $alert) {
                if (empty($alert['skylogsStatus']) || $alert['skylogsStatus'] == self::FIRE) {
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

                $text .= 'Data Source: '.$alert['dataSourceName']."\n";
                if (! empty($alert['labels'])) {
                    foreach ($needLabelArray as $label) {
                        if (! empty($alert['labels'][$label])) {
                            $text .= "$label : ".$alert['labels'][$label]."\n";
                        }
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

        $text .= 'date: '.Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram()
    {

        $result = [
            'message' => $this->defaultMessage(),
        ];
        if ($this->alertRule->enableAcknowledgeBtnInMessage() && $this->state == self::FIRE) {
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

        $text = 'Alert '.$this->alertRuleName;

        $text .= match ($this->state) {
            self::FIRE => ' fired',
            self::RESOLVED => ' resolved',
            default => '',
        };

        return $text;

    }
}
