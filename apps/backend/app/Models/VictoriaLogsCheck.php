<?php

namespace App\Models;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class VictoriaLogsCheck extends BaseModel implements Messageable
{
    use ProvidesDefaultChannelMessages;

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const CONDITION_TYPE_GREATER_OR_EQUAL = 'greaterOrEqual';

    public const CONDITION_TYPE_LESS_OR_EQUAL = 'lessOrEqual';

    public const RESOLVED = 1;

    public const FIRE = 2;

    public const NOTIFICATION = 3;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function defaultMessage(): string
    {

        $text = $this->alertRule->name."\n\n";
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

        $text .= 'Data Source: '.$this->alertRule->dataSource->name."\n\n";

        $text .= 'date: '.Jalalian::now()->format('Y/m/d');

        return $text;
    }
}
