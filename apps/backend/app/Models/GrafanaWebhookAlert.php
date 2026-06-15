<?php

namespace App\Models;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use MongoDB\Laravel\Relations\BelongsTo;

class GrafanaWebhookAlert extends BaseModel implements Messageable
{
    use ProvidesDefaultChannelMessages;

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
        $alertRule = $this->alertRule;

        if (! $alertRule) {
            return '';
        }

        return AlertMessageTemplateRenderer::make()->renderDefault($alertRule, $this->toArray());
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

    public function baleMessage()
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
}
