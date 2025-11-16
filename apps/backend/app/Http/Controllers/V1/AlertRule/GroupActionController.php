<?php

namespace App\Http\Controllers\V1\AlertRule;

use App\Http\Controllers\Controller;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupActionController extends Controller
{
    public function __construct(
        protected AlertRuleService $alertRuleService,
        protected EndpointService $endpointService,
    ) {}

    public function AddUserAccessNotify(Request $request)
    {

        $alertRules = $this->alertRuleService->getAlertRules($request);
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        foreach ($alertRules as $alert) {
            if ($this->alertRuleService->hasAdminAccessAlert($user, $alert)) {

                if ($request->has('userIds') && ! empty($request->post('userIds'))) {
                    foreach ($request->userIds as $userId) {
                        $alert->push('userIds', $userId, true);
                    }
                    $alert->save();
                }

                if ($request->has('teamIds') && ! empty($request->post('teamIds'))) {
                    foreach ($request->teamIds as $teamId) {
                        $alert->push('teamIds', $teamId, true);
                    }
                    $alert->save();
                }

            }

            if ($request->has('endpointIds') && ! empty($request->post('endpointIds'))) {

                $selectableEndpointIds = app(EndpointService::class)->selectableUserEndpoint($user, $alert)->pluck('id');
                foreach ($request->endpointIds as $endpointId) {

                    $hasAccessToAdd = $isAdmin || $selectableEndpointIds->contains($endpointId);

                    if ($hasAccessToAdd) {
                        $alert->push('endpoint_ids', $endpointId, true);
                        $alert->push('endpointIds', $endpointId, true);
                    }

                }
                $alert->save();

            }

        }

        return response()->json(['status' => true]);
    }

    public function Silent(Request $request)
    {

        $alertRules = $this->alertRuleService->getAlertRules($request);

        foreach ($alertRules as $alert) {
            if (! $alert->isSilent()) {
                $alert->silent();
            }
        }

        return response()->json(['status' => true]);

    }

    public function UnSilent(Request $request)
    {

        $alertRules = $this->alertRuleService->getAlertRules($request);

        foreach ($alertRules as $alert) {
            if ($alert->isSilent()) {
                $alert->unSilent();
            }
        }

        return response()->json(['status' => true]);

    }

    public function Delete(Request $request)
    {

        $alertRules = $this->alertRuleService->getAlertRules($request);
        $user = Auth::user();

        foreach ($alertRules as $alert) {

            $this->alertRuleService->deleteForUser($user, $alert);

        }

        return response()->json(['status' => true]);

    }
}
