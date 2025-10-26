<?php

namespace App\Models;

use App\Interfaces\Messageable;
use MongoDB\Laravel\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class ElasticCheck extends BaseModel implements Messageable
{
    public $timestamps = true;

    public static $title = 'Elastic Check';

    public static $KEY = 'elastic_check';

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

        if (! empty($this->dataviewName)) {
            $text .= 'Data View: '.$this->dataviewName."\n\n";
        }

        $text .= 'date: '.Jalalian::now()->format('Y/m/d');

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

    public function discordMessage(): string
    {

        return $this->defaultMessage();

    }

    public function callMessage(): string
    {

        return $this->defaultMessage();

    }
}
