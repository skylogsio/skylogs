<?php

use App\Enums\Constants;
use App\Http\Controllers\Cluster\SyncController;
use App\Http\Controllers\V1\AlertRule\AccessUserController;
use App\Http\Controllers\V1\AlertRule\AlertingController;
use App\Http\Controllers\V1\AlertRule\CreateDataController;
use App\Http\Controllers\V1\AlertRule\GroupActionController;
use App\Http\Controllers\V1\AlertRule\NotifyController;
use App\Http\Controllers\V1\AlertRule\PrometheusController;
use App\Http\Controllers\V1\AlertRule\TagsController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\Config\SkylogsController;
use App\Http\Controllers\V1\Config\TelegramController;
use App\Http\Controllers\V1\DataSourceController;
use App\Http\Controllers\V1\EndpointController;
use App\Http\Controllers\V1\Profile\AssetController;
use App\Http\Controllers\V1\SkylogsInstanceController;
use App\Http\Controllers\V1\StatusController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\Webhooks\ApiAlertController;
use App\Http\Controllers\V1\Webhooks\WebhookAlertsController;
use Illuminate\Support\Facades\Route;

Route::prefix('cluster')
    ->controller(SyncController::class)
    ->middleware('clusterAuth')
    ->group(function () {

        Route::get('/sync-data', 'Data')->name('cluster.data');

    });

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('status/all', [StatusController::class, 'Status'])->name('status.all');
    Route::get('alert-rule/acknowledgeL/{id}', [AlertingController::class, 'AcknowledgeLoginLink'])->name('acknowledgeLink');

    Route::middleware('apiAuth')->controller(ApiAlertController::class)->group(function () {
        Route::post('fire-alert', 'FireAlert')->name('webhook.api.fire');
        Route::post('resolve-alert', 'ResolveAlert')->name('webhook.api.resolve');
        Route::post('status-alert', 'StatusAlert')->name('webhook.api.status');
        Route::post('notification-alert', 'NotificationAlert')->name('webhook.notification');
        Route::post('stop-alert', 'ResolveAlert')->name('webhook.api.stop');
    });

    Route::middleware('webhookAuth')->controller(WebhookAlertsController::class)->group(function () {

        //        Route::post("/metabase-alert/{token}", 'MetabaseWebhook')->name("webhook.metabase");
        Route::post('/sentry-alert/{token}', 'SentryWebhook')->name('webhook.sentry');
        Route::post('/splunk-alert/{token}', 'SplunkWebhook')->name('webhook.splunk');
        Route::post('/zabbix-alert/{token}', 'ZabbixWebhook')->name('webhook.zabbix');
        Route::post('/grafana-alert/{token}', 'GrafanaWebhook')->name('webhook.grafana');
        Route::post('/pmm-alert/{token}', 'PmmWebhook')->name('webhook.pmm');

    });

    Route::middleware('auth')->group(function () {

        Route::prefix('auth')
            ->controller(AuthController::class)
            ->group(function () {
                Route::post('logout', 'logout');
                Route::post('refresh', 'refresh');
                Route::post('me', 'me');
            });

        Route::prefix('/user')
            ->controller(UserController::class)
            ->middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/all', 'All');
                Route::get('/{id}', 'Show');
                Route::post('/', 'Create');
                Route::middleware('role:'.Constants::ROLE_OWNER->value)->post('/changeOwner', 'ChangeOwnerShipOfData');
                Route::put('/pass/{id}', 'ChangePassword');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/endpoint')
            ->controller(EndpointController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/indexFlow', 'IndexFlow');
                Route::get('/createFlowEndpoints', 'EndpointsToCreateFlow');
                Route::get('/{id}', 'Show');
                Route::post('/', 'Create');
                Route::post('/sendOTP', 'SendOTPCode');
                Route::put('/{id}', 'Update');
                Route::post('/changeOwner/{id}', 'ChangeOwner');
                Route::delete('/{id}', 'Delete');
            });
        Route::prefix('/skylogs-instance')
            ->controller(SkylogsInstanceController::class)
            ->middleware('role:'.Constants::ROLE_OWNER->value)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/status/{id}', 'IsConnected');
                Route::get('/{id}', 'Show');
                Route::post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/data-source')
            ->controller(DataSourceController::class)
            ->middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/types', 'GetTypes');
                Route::get('/status/{id}', 'IsConnected');
                Route::get('/{id}', 'Show');
                Route::post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/status')
            ->controller(StatusController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/{id}', 'Show');
                Route::post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/alert-rule')
            ->controller(AlertingController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/types', 'GetTypes');
                Route::get('/history/{id}', 'History');
                Route::get('/triggered/{id}', 'FiredAlerts');
                Route::get('/filter-endpoints', 'FilterEndpoints');

                Route::prefix('/create-data')
                    ->controller(CreateDataController::class)
                    ->group(function () {
                        Route::get('/', 'CreateData');
                        Route::get('/data-source/{type}', 'DataSources');
                        Route::get('/zabbix', 'ZabbixData');
                        Route::get('/rules', 'Rules');
                        Route::get('/labels', 'Labels');
                        Route::get('/label-values/{label}', 'LabelValues');
                    });

                Route::prefix('/group-action')
                    ->controller(GroupActionController::class)
                    ->group(function () {
                        Route::post('/silent', 'Silent');
                        Route::post('/unsilent', 'UnSilent');
                        Route::post('/delete', 'Delete');
                        Route::post('/add-user-notify', 'AddUserAccessNotify');
                    });

                Route::get('/{id}', 'Show');
                Route::post('/', 'Store');
                Route::post('/silent/{id}', 'Silent');
                Route::post('/pin/{id}', 'Pin');
                Route::post('/acknowledge/{id}', 'Acknowledge');
                Route::post('/resolve/{id}', 'ResolveAlert');
                Route::put('/{id}', 'StoreUpdate');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/prometheus')
            ->controller(PrometheusController::class)
            ->group(function () {
                Route::get('/rules', 'Rules');
                Route::get('/labels', 'Labels');
                Route::get('/triggered', 'Triggered');
                Route::get('/label-values/{label}', 'LabelValues');
            });

        Route::prefix('/alert-rule-tag')
            ->controller(TagsController::class)
            ->group(function () {
                Route::get('/', 'All');
                Route::get('/{id}', 'Create');
                Route::put('/{id}', 'Store');
            });
        Route::prefix('/alert-rule-notify')
            ->controller(NotifyController::class)
            ->group(function () {
                Route::get('/{id}', 'Create');
                Route::put('/{id}', 'Store');
                Route::delete('/{alertId}/{endpointId}', 'Delete');

                Route::post('/test/{id}', 'Test');

                Route::get('/batchAlert', 'CreateBatch');
                Route::put('/batchAlert', 'StoreBatch');

            });
        Route::prefix('/alert-rule-user')
            ->controller(AccessUserController::class)
            ->group(function () {
                Route::get('/{id}', 'CreateData');
                Route::put('/{id}', 'Store');
                Route::delete('/{alertId}/{userId}', 'Delete');

            });

        Route::prefix('/profile')
            ->middleware('role:'.Constants::ROLE_OWNER->value)
            ->group(function () {
                Route::prefix('/asset')
                    ->controller(AssetController::class)
                    ->group(function () {
                        Route::get('/', 'Index');
                        Route::get('/{id}', 'Show');
                        Route::post('/', 'Create');
                        Route::put('/{id}', 'Update');
                        Route::delete('/{id}', 'Delete');
                    });

            });

        Route::prefix('/config')
            ->middleware('role:'.Constants::ROLE_OWNER->value)
            ->group(function () {

                Route::prefix('/skylogs')
                    ->controller(SkylogsController::class)
                    ->group(function () {
                        Route::get('/cluster', 'ClusterType');
                        Route::post('/cluster', 'StoreClusterType');
                    });

                Route::prefix('/telegram')
                    ->controller(TelegramController::class)
                    ->group(function () {
                        Route::get('/', 'Index');

                        Route::get('/{id}', 'Show');
                        Route::post('/', 'Create');
                        Route::post('/deactivate', 'Deactivate');
                        Route::post('/activate/{id}', 'Activate');
                        Route::put('/{id}', 'Update');
                        Route::delete('/{id}', 'Delete');
                    });

            });

    });

});
