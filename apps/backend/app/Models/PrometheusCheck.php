<?php

namespace App\Models;

use App\Concerns\ProvidesDefaultChannelMessages;
use App\Interfaces\Messageable;
use App\Models\DataSource\DataSource;
use App\Services\AlertMessage\AlertMessageTemplateRenderer;
use MongoDB\Laravel\Relations\BelongsTo;

class PrometheusCheck extends BaseModel implements Messageable
{
    use ProvidesDefaultChannelMessages;

    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const RESOLVED = 1;

    public const FIRE = 2;

    public const NOTIFICATION = 3;

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

        $countResolve = 0;
        $countFire = 0;

        foreach ($this->alerts as $alert) {
            if ($alert['skylogsStatus'] == self::RESOLVED) {
                $countResolve++;
            } elseif ($alert['skylogsStatus'] == self::FIRE) {
                $countFire++;
            }
        }
        PrometheusHistory::create(
            [
                'alertRuleId' => $this->alertRuleId,
                'alerts' => $this->alerts,
                'state' => $this->state,
                'countResolve' => $countResolve,
                'countFire' => $countFire,
            ]
        );
    }

    public function saveWithHistory($matchedAlerts)
    {
        $savedAlerts = collect(\Arr::dot($this->alerts));
        $currentAlerts = collect(\Arr::dot($matchedAlerts));

        $diffs = $savedAlerts->diffAssoc($currentAlerts);
        $diffs2 = $currentAlerts->diffAssoc($savedAlerts);
        if ($diffs->isNotEmpty() || $diffs2->isNotEmpty()) {
            PrometheusHistory::create(
                [
                    'alertRuleId' => $this->alertRuleId,
                    'alerts' => $this->alerts,
                    'state' => $this->state,
                ]
            );
        }

        $this->alerts = $matchedAlerts;
        $this->save();

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

    public function baleMessage()
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
