<?php

namespace App\Services;

class ServiceAlertRuleService
{
    public static function GetAlertRules()
    {
        //        $prometheusAlertRules = PrometheusInstanceService::getRules();
        $sentryAlertRules = SentryService::getIssueRules();

        return $sentryAlertRules;
    }
}
