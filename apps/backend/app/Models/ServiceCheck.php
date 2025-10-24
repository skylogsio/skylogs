<?php

namespace App\Models;

use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class ServiceCheck extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'ZabbixService Check';

    public static $KEY = 'service_check';

    protected $guarded = ['id', '_id'];

    public const DOWN = 1;

    public const UP = 2;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', '_id');
    }

    public function getUrl()
    {
        return $this->service->getHealthUrl();
    }

    public function defaultMessage(): string
    {

        $text = $this->alertRule->alertname."\n\n";
        if (! empty($this->state)) {
            switch ($this->state) {
                case self::UP:
                    $text .= 'State: UP ✅'."\n\n";
                    break;
                case self::DOWN:
                    $text .= 'State: DOWN 🔥'."\n\n";
                    break;
            }
        }

        $text .= 'date: '.Jalalian::now()->format('Y/m/d');

        return $text;
    }

    public function telegram(): string
    {
        return $this->defaultMessage();
    }

    public function matterMostMessage(): string
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
