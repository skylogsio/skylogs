<?php

namespace App\Observers;

use App\Models\AlertRule;
use App\Services\DataSourceService;
use App\Services\AlertRuleService;

class AlertRuleObserver {

    public function created(AlertRule $alertRule): void {
        app(AlertRuleService::class)->flushCache();
    }

    public function updated(AlertRule $alertRule): void {
        app(AlertRuleService::class)->update($alertRule);
        app(AlertRuleService::class)->flushCache();
    }

    public function deleted(AlertRule $alertRule): void {
        app(AlertRuleService::class)->flushCache();
    }

    public function restored(AlertRule $alertRule): void {
        app(AlertRuleService::class)->flushCache();
    }

    public function forceDeleted(AlertRule $alertRule): void {
        app(AlertRuleService::class)->flushCache();
    }

}
