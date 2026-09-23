<?php

namespace App\Models;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class HealthCheck extends BaseModel implements Messageable
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

    public function defaultMessage(): string
    {

        $name = $this->alertRule->name ?: $this->alertRule->alertname;
        $text = $name."\n\n";
        if (! empty($this->state)) {
            switch ((int) $this->state) {
                case self::UP:
                    $text .= 'State: UP ✅'."\n\n";
                    break;
                case self::DOWN:
                    $text .= 'State: DOWN 🔥'."\n\n";
                    if (! empty($this->url)) {
                        $text .= 'url: '.$this->url."\n\n";
                    }
                    if (! empty($this->lastError)) {
                        $text .= 'error: '.$this->lastError."\n\n";
                    }
                    break;
            }
        }

        $text .= 'date: '.Jalalian::now()->format('Y/m/d');

        return $text;
    }
}
