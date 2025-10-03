<?php

namespace App\Observers;

use App\Models\Endpoint;
use App\Services\DataSourceService;
use App\Services\EndpointService;

class EndpointObserver {

    public function created(Endpoint $endpoint): void {
        app(EndpointService::class)->flushCache();
    }

    public function updated(Endpoint $endpoint): void {
        app(EndpointService::class)->flushCache();
    }

    public function deleted(Endpoint $endpoint): void {
        app(EndpointService::class)->deleteEndpointOfAlertRules($endpoint);
        app(EndpointService::class)->flushCache();
    }

    public function restored(Endpoint $endpoint): void {
        app(EndpointService::class)->flushCache();
    }

    public function forceDeleted(Endpoint $endpoint): void {
        app(EndpointService::class)->flushCache();
    }

}
