<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use App\Services\SendNotifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotifyController extends Controller
{
    public function Create($id)
    {
        $user = auth()->user();
        $alert = AlertRule::where('_id', $id)->firstOrFail();

        if (! app(AlertRuleService::class)->hasUserAccessAlert($user, $alert)) {
            abort(403);
        }

        $endpointService = app(EndpointService::class);
        $selectableEndpoints = $endpointService->selectableUserEndpoint($user, $alert);

        $alertEndpoints = collect();
        if (! empty($alert->endpointIds)) {
            $alertEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)->get();
            foreach ($alertEndpoints as $endpoint) {
                $endpoint->canRemove = $endpointService->userCanRemoveAlertEndpoint($user, $alert, $endpoint);
            }
        }

        return response()->json(compact('alertEndpoints', 'selectableEndpoints'));
    }

    public function CreateBatch()
    {

        $selectableEndpoints = app(EndpointService::class)->selectableUserEndpoint(Auth::user());

        return response()->json(compact('selectableEndpoints'));
    }

    public function Test($id)
    {
        $user = Auth::user();
        $alert = AlertRule::where('_id', $id)->firstOrFail();
        $access = app(AlertRuleService::class)->hasUserAccessAlert($user, $alert);
        if (! $access) {
            abort(403);
        }
        app(SendNotifyService::class)->createNotify(SendNotifyJob::ALERT_RULE_TEST, $alert, $alert->_id);

        return response()->json(['status' => true]);
    }

    public function Store(Request $request, $id)
    {

        $currentUser = Auth::user();
        if ($request->has('endpointIds') && ! empty($request->post('endpointIds'))) {

            $alert = AlertRule::where('_id', $id)->firstOrFail();

            if (app(AlertRuleService::class)->hasUserAccessAlert($currentUser, $alert)) {
                foreach (app(EndpointService::class)->assignableEndpointIds($currentUser, $request->endpointIds) as $endpointId) {
                    $alert->push('endpointIds', $endpointId, true);
                }
            }

            $alert->save();

        }

        return response()->json(['status' => true]);
    }

    public function StoreBatch(Request $request)
    {

        $currentUser = Auth::user();
        $alertIds = [];
        if ($request->has('alertIds') && ! empty($request->post('alertIds'))) {
            $alertIds = $request->post('alertIds');
        }
        if ($request->has('endpoints') && ! empty($request->post('endpoints'))) {

            foreach ($alertIds as $id) {
                $alert = AlertRule::where('_id', $id)->first();

                if ($alert === null || ! app(AlertRuleService::class)->hasUserAccessAlert($currentUser, $alert)) {
                    continue;
                }

                foreach (app(EndpointService::class)->assignableEndpointIds($currentUser, $request->endpoints) as $endpointId) {
                    $alert->push('endpointIds', $endpointId, true);
                }

                $alert->save();
            }

        }

        return response()->json(['status' => true]);
    }

    public function Delete($alertId, $endpointId)
    {
        $user = Auth::user();
        $alert = AlertRule::where('_id', $alertId)->firstOrFail();

        if (! app(AlertRuleService::class)->hasUserAccessAlert($user, $alert)) {
            abort(403);
        }

        $endpoint = Endpoint::where('_id', $endpointId)->first();
        $canRemove = app(AlertRuleService::class)->userOwnsAlert($user, $alert)
            || $user->isAdmin()
            || ($endpoint !== null && app(EndpointService::class)->userCanUseEndpoint($user, $endpoint));

        if (! $canRemove) {
            abort(403);
        }

        $alert->pull('endpointIds', $endpointId);
        $alert->save();

        return response()->json(['status' => true]);
    }
}
