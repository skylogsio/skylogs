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

        $alert = AlertRule::where('_id', $id)->firstOrFail();

        $selectableEndpoints = app(EndpointService::class)->selectableUserEndpoint(auth()->user(), $alert);

        $alertEndpoints = [];
        $selectableEndpointsIds = $selectableEndpoints->pluck('id')->toArray();
        if (! empty($alert->endpointIds) && ! empty($selectableEndpointsIds)) {
            $alertEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)
                ->whereIn('_id', $selectableEndpointsIds)
                ->get();
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
        SendNotifyService::CreateNotify(SendNotifyJob::ALERT_RULE_TEST, $alert, $alert->_id);

        return response()->json(['status' => true]);
    }

    public function Store(Request $request, $id)
    {

        $currentUser = Auth::user();
        $isAdmin = $currentUser->isAdmin();
        if ($request->has('endpointIds') && ! empty($request->post('endpointIds'))) {

            $alert = AlertRule::where('_id', $id)->firstOrFail();

            $selectableEndpointIds = app(EndpointService::class)->selectableUserEndpoint($currentUser, $alert)->pluck('id');
            foreach ($request->endpointIds as $endpointId) {

                $hasAccessToAdd = $isAdmin || $selectableEndpointIds->contains($endpointId);

                if ($hasAccessToAdd) {
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
        $isAdmin = $currentUser->isAdmin();
        $alertIds = [];
        if ($request->has('alertIds') && ! empty($request->post('alertIds'))) {
            $alertIds = $request->post('alertIds');
        }
        if ($request->has('endpoints') && ! empty($request->post('endpoints'))) {

            foreach ($alertIds as $id) {
                $alert = AlertRule::where('_id', $id)->first();

                $selectableEndpointIds = app(EndpointService::class)->selectableUserEndpoint($currentUser, $alert)->pluck('id');
                foreach ($request->endpoints as $endpointId) {

                    $hasAccessToAdd = $isAdmin || $selectableEndpointIds->contains($endpointId);

                    if ($hasAccessToAdd) {
                        $alert->push('endpointIds', $endpointId, true);
                    }

                }

                $alert->save();
            }

        }

        return response()->json(['status' => true]);
    }

    public function Delete($alertId, $endpointId)
    {

        $alert = AlertRule::where('_id', $alertId)->firstOrFail();
        $alert->pull('endpointIds', $endpointId);
        $alert->save();

        return response()->json(['status' => true]);
    }
}
