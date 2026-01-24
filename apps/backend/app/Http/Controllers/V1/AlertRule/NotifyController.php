<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotifyJob;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
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
        $currentUser = \Auth::user();

        $alertUserIds = $alert->userIds ?? [];
        $selectableEndpoints = [];
        if ($currentUser->isAdmin()) {
            $selectableEndpoints = Endpoint::get();
        } elseif ($alert->userId == $currentUser->_id) {
            $selectableEndpoints = Endpoint::where('userId', $currentUser->_id)->orWhere('isPublic', true)->get();
        } elseif (in_array($currentUser->_id, $alertUserIds)) {
            $selectableEndpoints = Endpoint::where('userId', $currentUser->_id)->get();
        }

        $alertEndpoints = [];
        if (! empty($alert->endpointIds)) {
            if ($currentUser->isAdmin()) {
                $alertEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)->get();
            } elseif ($alert->userId == $currentUser->_id) {
                $alertEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)->where(function ($query) use ($currentUser) {
                    return $query->where('userId', $currentUser->id)->orWhere('isPublic', true);
                })->get();
            } elseif (in_array($currentUser->_id, $alertUserIds)) {
                $alertEndpoints = Endpoint::whereIn('_id', $alert->endpointIds)->where('userId', $currentUser->_id)->get();
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
        SendNotifyService::CreateNotify(SendNotifyJob::ALERT_RULE_TEST, $alert, $alert->_id);

        return response()->json(['status' => true]);
    }

    public function Store(Request $request, $id)
    {

        $currentUser = Auth::user();
        $isAdmin = $currentUser->isAdmin();
        if ($request->has('endpointIds') && ! empty($request->post('endpointIds'))) {
            $adminUserId = User::where('username', 'admin')->first()->_id;

            $alert = AlertRule::where('_id', $id)->firstOrFail();

            foreach ($request->endpointIds as $end) {
                $hasAccessToAdd = false;
                $alertUserIds = $alert->userIds ?? [];
                if ($isAdmin) {
                    $hasAccessToAdd = true;
                } elseif ($alert->userId == $currentUser->_id) {
                    $hasAccessToAdd = Endpoint::where('id', $end)->where(function ($query) use ($currentUser) {
                        return $query->where('userId', $currentUser->id)->orWhere('isPublic', true);
                    })->get();
                } elseif (in_array($currentUser->_id, $alertUserIds)) {
                    $hasAccessToAdd = Endpoint::where('id', $end)->where('userId', $currentUser->_id)->get();
                }
                if ($hasAccessToAdd) {
                    $alert->push('endpointIds', $end, true);
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
            $adminUserId = User::where('username', 'admin')->first()->_id;
            $adminEndpoints = Endpoint::where('userId', $adminUserId)
                ->get()
                ->pluck('_id')->toArray();

            foreach ($alertIds as $id) {
                $alert = AlertRule::where('_id', $id)->first();

                foreach ($request->endpoints as $endpointId) {
                    $hasAccessToAdd = false;
                    $alertUserIds = $alert->userIds ?? [];
                    if ($isAdmin) {
                        $hasAccessToAdd = true;
                    } elseif ($alert->userId == $currentUser->_id) {
                        $hasAccessToAdd = Endpoint::where('id', $endpointId)->where(function ($query) use ($currentUser) {
                            return $query->where('userId', $currentUser->id)->orWhere('isPublic', true);
                        })->get();
                    } elseif (in_array($currentUser->_id, $alertUserIds)) {
                        $hasAccessToAdd = Endpoint::where('id', $endpointId)->where('userId', $currentUser->_id)->get();
                    }
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
