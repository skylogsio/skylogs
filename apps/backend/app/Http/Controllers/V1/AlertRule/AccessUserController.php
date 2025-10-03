<?php

namespace App\Http\Controllers\V1\AlertRule;


use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\User;
use App\Services\AlertRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccessUserController extends Controller
{


    public function __construct(protected AlertRuleService $alertRuleService)
    {
    }

    public function CreateData($id)
    {

        $alert = AlertRule::where('_id', $id)->firstOrFail();
        $currentUser = \Auth::user();


        if (!$this->alertRuleService->hasAdminAccessAlert($currentUser, $alert)) {
            abort(403);
        }

        $selectableUsers = User::whereNotIn('_id', [$currentUser->id,])->get();

        $alertUsers = [];
        if (!empty($alert->userIds))
            $alertUsers = User::whereIn("_id", $alert->userIds)->get();

        return response()->json(compact('alertUsers', 'selectableUsers'));
    }


    public function Store(Request $request, $id)
    {

        $currentUser = Auth::user();
        $alert = AlertRule::where('_id', $id)->firstOrFail();

        if (!$this->alertRuleService->hasAdminAccessAlert($currentUser, $alert)) {
            abort(403);
        }

        if ($request->has("user_ids") && !empty($request->post("user_ids"))) {

            foreach ($request->user_ids as $userId) {
                $alert->push("userIds", $userId, true);
            }
            $alert->save();
        }

        return response()->json(['status' => true]);
    }

    public function Delete($alertId, $userId)
    {

        $alert = AlertRule::where('_id', $alertId)->firstOrFail();
        if (!$this->alertRuleService->hasAdminAccessAlert(Auth::user(), $alert)) {
            abort(403);
        }

        $alert->pull("userIds", $userId);
        if (!empty($alert->user_ids)) {
            $alert->pull("user_ids", $userId);
        }
        $alert->save();

        return response()->json(['status' => true]);
    }


}
