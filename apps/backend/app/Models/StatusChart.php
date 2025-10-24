<?php

namespace App\Models;

use App\Helpers\Constants;
use MongoDB\Laravel\Relations\BelongsTo;

class StatusChart extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Status Chart';

    public static $KEY = 'status_chart';

    protected $guarded = ['id', '_id'];

    public const CRITICAL = 1;

    public const RESOLVED = 2;

    public const WARNING = 3;

    public const UNKNOWN = 4;

    public function alertRule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alertRuleId', '_id');
    }

    public static function GenerateArrays($statusCharts): array
    {
        $result = [];
        foreach ($statusCharts as $item) {

            switch ($item->status) {
                case self::RESOLVED:
                    $color = Constants::COLOR_GREEN;
                    break;
                case self::WARNING:
                    $color = Constants::COLOR_YELLOW;
                    break;
                case self::CRITICAL:
                    $color = Constants::COLOR_RED;
                    break;
                case self::UNKNOWN:
                    $color = Constants::COLOR_BLUE;
                    break;
                default:
                    break;
            }

            $newItem = [];
            $newItem['name'] = $item->alertname;
            $newItem['data'] = [[
                'x' => $item->alertname,
                'y' => [
                    $item->from,
                    $item->to,
                ],
                'fillColor' => $color,
            ]];
            $result[] = $newItem;
        }

        return $result;
    }

    public static function GetStatusSentry($sentryWebhook)
    {
        switch ($sentryWebhook->action) {
            case AlertRule::CRITICAL:
                return self::CRITICAL;
            case AlertRule::WARNING:
                return self::WARNING;
            case AlertRule::RESOlVED:
                return self::RESOLVED;
            case AlertRule::UNKNOWN:
            default:
                return self::UNKNOWN;
        }

    }

    public static function GetStatusApi($apiStatusHistory)
    {
        switch ($apiStatusHistory->state) {
            case ApiAlertStatusHistory::FIRE:
                return self::CRITICAL;
            case ApiAlertStatusHistory::RESOLVED:
                return self::RESOLVED;
            default:
                return self::UNKNOWN;
        }

    }

    public static function GetStatusPrometheus($history)
    {

        /*    public const RESOLVED = 1;
    public const FIRE = 2;
    public const NOTIFICATION = 3;
*/
        switch ($history->state) {
            case PrometheusHistory::FIRE:
                return self::CRITICAL;
            case PrometheusHistory::RESOLVED:
                return self::RESOLVED;
            default:
                return self::UNKNOWN;
        }

    }

    public static function GetStatusHealth($history)
    {

        switch ($history->state) {
            case HealthCheck::DOWN:
                return self::CRITICAL;
            case HealthCheck::UP:
                return self::RESOLVED;
            default:
                return self::UNKNOWN;
        }

    }

    public static function GetStatusElastic($history)
    {

        switch ($history->state) {
            case ElasticHistory::FIRE:
                return self::CRITICAL;
            case ElasticHistory::RESOLVED:
                return self::RESOLVED;
            default:
                return self::UNKNOWN;
        }

    }
}
