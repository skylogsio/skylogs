<?php

use App\Enums\Constants;
use App\Http\Controllers\Cluster\SyncController;
use App\Http\Controllers\Ha\ConfigSyncController;
use App\Http\Controllers\Ha\HistorySyncController;
use App\Http\Controllers\Ha\StateController;
use App\Http\Controllers\V1\AlertRule\AccessUserController;
use App\Http\Controllers\V1\AlertRule\AlertingController;
use App\Http\Controllers\V1\AlertRule\BehaviorRuleController;
use App\Http\Controllers\V1\AlertRule\CreateDataController;
use App\Http\Controllers\V1\AlertRule\GroupActionController;
use App\Http\Controllers\V1\AlertRule\NotifyController;
use App\Http\Controllers\V1\AlertRule\PrometheusController;
use App\Http\Controllers\V1\AlertRule\TagsController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\Config\CallController;
use App\Http\Controllers\V1\Config\EmailController;
use App\Http\Controllers\V1\Config\SkylogsController;
use App\Http\Controllers\V1\Config\SmsController;
use App\Http\Controllers\V1\Config\TelegramController;
use App\Http\Controllers\V1\DataSourceController;
use App\Http\Controllers\V1\EndpointController;
use App\Http\Controllers\V1\Incident\ActionItemController;
use App\Http\Controllers\V1\Incident\DocumentController;
use App\Http\Controllers\V1\Incident\PostMortemController;
use App\Http\Controllers\V1\Incident\TimelineController;
use App\Http\Controllers\V1\IncidentActionItemController;
use App\Http\Controllers\V1\IncidentController;
use App\Http\Controllers\V1\IncidentPolicyController;
use App\Http\Controllers\V1\Profile\AssetController;
use App\Http\Controllers\V1\RunbookController;
use App\Http\Controllers\V1\SkylogsInstanceController;
use App\Http\Controllers\V1\StatusController;
use App\Http\Controllers\V1\TeamController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\Webhooks\ApiAlertController;
use App\Http\Controllers\V1\Webhooks\WebhookAlertsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to Skylogs!']);
});

Route::prefix('cluster')
    ->controller(SyncController::class)
    ->middleware('clusterAuth')
    ->group(function () {
        Route::get('/sync-data', 'Data')->name('cluster.data');
    });

/*
| High availability, spoken between the nodes of one cluster and their Raft
| sidecars. Deliberately outside v1: this is not the client API and it does not
| version with it.
*/
Route::prefix('ha')
    ->middleware('haNodeAuth')
    ->group(function () {
        Route::post('/apply', [StateController::class, 'apply'])->name('ha.apply');
        Route::get('/config-sync', [ConfigSyncController::class, 'show'])->name('ha.configSync');
        Route::get('/history-sync', [HistorySyncController::class, 'show'])->name('ha.historySync');
    });

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::middleware('clusterProxy')->get('status/all', [StatusController::class, 'Status'])->name('status.all');
    Route::get('alert-rule/acknowledgeL/{id}', [AlertingController::class, 'AcknowledgeLoginLink'])->name('acknowledgeLink');

    /*
    || Incident document downloads. Outside the JWT group on purpose: the expiring
    || signature is the credential, which is what lets a browser fetch an attachment
    || directly from an <img> or a download link.
    */
    Route::get('incident-document/{documentId}/download', [DocumentController::class, 'download'])
        ->middleware('signed')
        ->name('incident.document.download')
        ->where('documentId', '[0-9a-fA-F]{24}');

    Route::middleware(['apiAuth', 'throttle:api-alert'])
        ->controller(ApiAlertController::class)
        ->group(function () {
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

    Route::middleware(['clusterAgentValidate', 'auth', 'clusterProxy'])->group(function () {

        Route::prefix('auth')
            ->controller(AuthController::class)
            ->group(function () {
                Route::post('logout', 'logout');
                Route::post('refresh', 'refresh');
                Route::post('me', 'me');
                Route::post('pass', 'ChangePassword');
            });

        Route::prefix('/user')
            ->controller(UserController::class)
            ->group(function () {
                Route::get('/all', 'All');
                Route::middleware('role:'.Constants::ROLE_OWNER->value)->post('/changeOwner', 'ChangeOwnerShipOfData');
                Route::middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)->group(function () {
                    Route::get('/', 'Index');
                    Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                    Route::post('/', 'Create');
                    Route::put('/pass/{id}', 'ChangePassword');
                    Route::put('/{id}', 'Update');
                    Route::delete('/{id}', 'Delete');
                });

            });

        Route::prefix('/endpoint')
            ->controller(EndpointController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/indexFlow', 'IndexFlow');
                Route::get('/createFlowEndpoints', 'EndpointsToCreateFlow');
                Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                Route::post('/', 'Create');
                Route::post('/sendOTP', 'SendOTPCode');
                Route::put('/{id}', 'Update');
                Route::post('/changeOwner/{id}', 'ChangeOwner');
                Route::delete('/{id}', 'Delete');
            });
        Route::prefix('/skylogs-instance')
            ->controller(SkylogsInstanceController::class)
            ->withoutMiddleware(['clusterProxy'])
            ->group(function () {

                Route::middleware('role:'.Constants::ROLE_OWNER->value)->group(function () {
                    Route::get('/', 'Index');
                    Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                    Route::post('/', 'Create');
                    Route::put('/{id}', 'Update');
                    Route::delete('/{id}', 'Delete');
                });

                Route::get('/all', 'All');
                Route::get('/status/{id}', 'IsConnected');

            });

        Route::prefix('/data-source')
            ->controller(DataSourceController::class)
            ->middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/status/{id}', 'IsConnected');
                Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                Route::get('/types', 'GetTypes');
                Route::post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/team')
            ->controller(TeamController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/all', 'All');
                Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                Route::middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)->post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)->delete('/{id}', 'Delete');
            });

        Route::prefix('/incident')
            ->controller(IncidentController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{id}', 'show')->where('id', '[0-9a-fA-F]{24}');
                Route::post('/', 'store');
                Route::put('/{id}', 'update')->where('id', '[0-9a-fA-F]{24}');
                Route::delete('/{id}', 'destroy')->where('id', '[0-9a-fA-F]{24}');
                Route::post('/{id}/acknowledge', 'acknowledge')->where('id', '[0-9a-fA-F]{24}');
                Route::post('/{id}/resolve', 'resolve')->where('id', '[0-9a-fA-F]{24}');
            });

        /*
        || Everything documented against one incident. Reading a sub-resource needs read
        || access to the incident and writing one needs write access to it, enforced by
        || IncidentSubResourceController rather than by route middleware.
        */
        Route::prefix('/incident/{incidentId}')
            ->where(['incidentId' => '[0-9a-fA-F]{24}'])
            ->group(function () {
                Route::prefix('/postmortem')
                    ->controller(PostMortemController::class)
                    ->group(function () {
                        Route::get('/', 'show');
                        Route::put('/', 'update');
                        Route::post('/publish', 'publish');
                    });

                Route::prefix('/timeline')
                    ->controller(TimelineController::class)
                    ->group(function () {
                        Route::get('/', 'index');
                        Route::post('/', 'store');
                    });

                Route::prefix('/document')
                    ->controller(DocumentController::class)
                    ->where(['documentId' => '[0-9a-fA-F]{24}'])
                    ->group(function () {
                        Route::get('/', 'index');
                        Route::post('/', 'store');
                        Route::get('/{documentId}/download-url', 'downloadUrl');
                        Route::delete('/{documentId}', 'destroy');
                    });

                Route::prefix('/action-item')
                    ->controller(ActionItemController::class)
                    ->where(['actionItemId' => '[0-9a-fA-F]{24}'])
                    ->group(function () {
                        Route::get('/', 'index');
                        Route::post('/', 'store');
                        Route::put('/{actionItemId}', 'update');
                        Route::delete('/{actionItemId}', 'destroy');
                    });
            });

        Route::get('/incident-action-item', [IncidentActionItemController::class, 'index']);

        Route::prefix('/incident-policy')
            ->controller(IncidentPolicyController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{id}', 'show')->where('id', '[0-9a-fA-F]{24}');
                Route::get('/{id}/export', 'export')->where('id', '[0-9a-fA-F]{24}');

                Route::middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
                    ->group(function () {
                        Route::post('/', 'store');
                        Route::put('/{id}', 'update')->where('id', '[0-9a-fA-F]{24}');
                        Route::post('/import', 'import');
                        Route::post('/validate', 'validateImport');
                        Route::delete('/{id}', 'destroy')->where('id', '[0-9a-fA-F]{24}');
                    });
            });

        Route::prefix('/runbook')
            ->controller(RunbookController::class)
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{id}', 'show')->where('id', '[0-9a-fA-F]{24}');

                Route::middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
                    ->group(function () {
                        Route::post('/', 'store');
                        Route::put('/{id}', 'update')->where('id', '[0-9a-fA-F]{24}');
                        Route::delete('/{id}', 'destroy')->where('id', '[0-9a-fA-F]{24}');
                    });
            });

        Route::prefix('/status')
            ->controller(StatusController::class)
            ->middleware('role:'.Constants::ROLE_OWNER->value.'|'.Constants::ROLE_MANAGER->value)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
                Route::post('/', 'Create');
                Route::put('/{id}', 'Update');
                Route::delete('/{id}', 'Delete');
            });

        Route::prefix('/alert-rule')
            ->controller(AlertingController::class)
            ->group(function () {
                Route::get('/', 'Index');
                Route::get('/all', 'All');
                Route::get('/types', 'GetTypes');
                Route::get('/status', 'AlertStatus');
                Route::get('/history/{id}', 'History');
                Route::get('/history/{id}/export', 'ExportHistory')->where('id', '[0-9a-fA-F]{24}');
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

                Route::get('/{id}', 'Show')->where('id', '[0-9a-fA-F]{24}');
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
        Route::prefix('/alert-rule-behavior-rule')
            ->controller(BehaviorRuleController::class)
            ->group(function () {
                Route::get('/selectable-alert-rules/{alertRuleId}', 'SelectableAlertRules')
                    ->where('alertRuleId', '[0-9a-fA-F]{24}');
                Route::get('/{alertRuleId}', 'Index')->where('alertRuleId', '[0-9a-fA-F]{24}');
                Route::post('/{alertRuleId}', 'Store')->where('alertRuleId', '[0-9a-fA-F]{24}');
                Route::put('/{alertRuleId}/{ruleId}', 'Update')
                    ->where(['alertRuleId' => '[0-9a-fA-F]{24}', 'ruleId' => '[0-9a-fA-F\\-]{36}']);
                Route::delete('/{alertRuleId}/{ruleId}', 'Delete')
                    ->where(['alertRuleId' => '[0-9a-fA-F]{24}', 'ruleId' => '[0-9a-fA-F\\-]{36}']);
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

                Route::prefix('/sms')
                    ->controller(SmsController::class)
                    ->group(function () {
                        Route::get('/', 'Index');

                        Route::get('/{id}', 'Show');
                        Route::post('/', 'Create');
                        Route::get('/providers', 'providers');
                        Route::post('/make-default/{id}', 'makeDefault');
                        Route::post('/make-backup/{id}', 'makeBackup');
                        Route::put('/{id}', 'Update');
                        Route::delete('/{id}', 'Delete');
                    });
                Route::prefix('/call')
                    ->controller(CallController::class)
                    ->group(function () {
                        Route::get('/', 'Index');

                        Route::get('/{id}', 'Show');
                        Route::post('/', 'Create');
                        Route::get('/providers', 'providers');
                        Route::post('/make-default/{id}', 'makeDefault');
                        Route::post('/make-backup/{id}', 'makeBackup');
                        Route::put('/{id}', 'Update');
                        Route::delete('/{id}', 'Delete');
                    });

                Route::prefix('/email')
                    ->controller(EmailController::class)
                    ->group(function () {
                        Route::get('/', 'Index');

                        Route::get('/{id}', 'Show');
                        Route::post('/', 'Create');
                        Route::post('/make-default/{id}', 'makeDefault');
                        Route::post('/make-backup/{id}', 'makeBackup');
                        Route::put('/{id}', 'Update');
                        Route::delete('/{id}', 'Delete');
                    });

            });

    });

});
