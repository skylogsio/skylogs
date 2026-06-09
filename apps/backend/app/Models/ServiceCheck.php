<?php

namespace App\Models;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class ServiceCheck extends BaseModel implements Messageable
{
    use ProvidesDefaultChannelMessages;

    public $timestamps = true;

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
}
