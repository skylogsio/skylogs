<?php

namespace App\Models;

use App\Interfaces\Messageable;
use App\Support\NotifyMessagePayload;
use MongoDB\Laravel\Relations\BelongsTo;

class Notify extends BaseModel implements Messageable
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    protected $casts = [];

    public const STATUS_CREATED = 1;

    public const STATUS_RUNNING = 2;

    public const STATUS_DONE = 3;

    public const STATUS_FAIL = 4;

    public const STATUS_SILENT = 5;

    public const STATUS_ACKNOWLEDGED = 6;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public function defaultMessage(): string
    {
        return $this->messagePayload()->defaultMessage();
    }

    public function telegram(): mixed
    {
        return $this->messagePayload()->telegram();
    }

    public function matterMostMessage(): mixed
    {
        return $this->messagePayload()->matterMostMessage();
    }

    public function teamsMessage(): mixed
    {
        return $this->messagePayload()->teamsMessage();
    }

    public function emailMessage(): mixed
    {
        return $this->messagePayload()->emailMessage();
    }

    public function smsMessage(): mixed
    {
        return $this->messagePayload()->smsMessage();
    }

    public function discordMessage(): mixed
    {
        return $this->messagePayload()->discordMessage();
    }

    public function callMessage(): mixed
    {
        return $this->messagePayload()->callMessage();
    }

    protected function messagePayload(): NotifyMessagePayload
    {
        return NotifyMessagePayload::fromStored(is_array($this->messages) ? $this->messages : []);
    }
}
