<?php

namespace App\Http\Controllers\V1\Webhooks;


use App\Http\Controllers\Controller;
use App\Jobs\SendNotifyJob;
use App\Models\MetabaseWebhookAlert;
use App\Models\SentryWebhookAlert;
use App\Models\ZabbixWebhookAlert;
use App\Services\GrafanaService;
use App\Services\SendNotifyService;
use App\Services\ZabbixService;
use Illuminate\Http\Request;


class WebhookAlertsController extends Controller
{

    public function __construct(protected ZabbixService $zabbixService)
    {
    }

    public function GrafanaWebhook(Request $request, $token)
    {

        $alertRules = $request->alertRules;
        $dataSource = $request->dataSource;

        $alerts = $request->alerts;

        foreach ($alerts as &$alert) {
            $alert['dataSourceId'] = $dataSource->id;
        }

        $matchedAlerts = GrafanaService::CheckMatchedAlerts($request->all(), $alerts, $alertRules);
        GrafanaService::SaveMatchedAlerts($dataSource, $request->all(), $matchedAlerts);

        return [
            "status" => true,
        ];
    }

    public function PmmWebhook(Request $request, $token)
    {


        $alertRules = $request->alertRules;
        $dataSource = $request->dataSource;

        $alerts = $request->alerts;

        foreach ($alerts as &$alert) {
            $alert['dataSourceId'] = $dataSource->id;
        }

        $matchedAlerts = GrafanaService::CheckMatchedAlerts($request->all(), $alerts, $alertRules);
        GrafanaService::SaveMatchedAlerts($dataSource, $request->all(), $matchedAlerts);

        return [
            "status" => true,
        ];
    }

    public function SentryWebhook(Request $request, $token)
    {

        $alertRules = $request->alertRules;
        $dataSource = $request->dataSource;

        $post = $request->post();

        $model = new SentryWebhookAlert();

        if ($model->CustomSave($dataSource, $alertRules, $post)) {

            SendNotifyService::CreateNotify(SendNotifyJob::SENTRY_WEBHOOK, $model, $model->alertRuleId);

            return [
                "status" => true,
            ];

        } else {

            return [
                "status" => false,
            ];

        }
    }

    public function ZabbixWebhook(Request $request, $token)
    {


        $alertRules = $request->alertRules;
        $dataSource = $request->dataSource;

        $post = $request->except(["dataSource", "alertRules"]);

        $this->zabbixService->checkAlertRules($dataSource, $alertRules, $post);

        return response()->json([
            "status" => true,
        ]);


    }

    public function SplunkWebhook(Request $request)
    {
        $post = $request->post();

        $file = fopen("text.txt", 'a+');
        fwrite($file, json_encode($request->post()));
        fwrite($file, "\n\n\n");
        fclose($file);
        return response()->json(['status' => true]);
//        $model = new SplunkWebhookAlert([
//            ...$post,
//        ]);

//        if ($model->CustomSave($post)) {

//            SendNotifyService::CreateNotify(SendNotifyJob::SPLUNK_WEBHOOK, $model, $model->alert_rule_id);

//            return [
//                "status" => true,
//            ];
//
//        } else {
//
//            return [
//                "status" => false,
//            ];
//
//        }
    }

    public function MetabaseWebhook(Request $request, $token)
    {


        $post = $request->post();

        $model = new MetabaseWebhookAlert();

        if ($model->CustomSave($post)) {

            SendNotifyService::CreateNotify(SendNotifyJob::METABASE_WEBHOOK, $model, $model->alertRuleId);

            return [
                "status" => true,
            ];

        } else {

            return [
                "status" => false,
            ];

        }

    }

}
