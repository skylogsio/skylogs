<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;

return [
    AppServiceProvider::class,
    PrometheusServiceProvider::class,
    HorizonServiceProvider::class,
];
