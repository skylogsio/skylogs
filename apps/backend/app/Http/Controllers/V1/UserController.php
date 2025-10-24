<?php

namespace App\Http\Controllers\V1;

use App\Enums\Constants;
use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use Hash;
use Illuminate\Http\Request;
use Validator;

class UserController extends Controller
{
    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = User::query();

        if ($request->filled('username')) {
            $data->where('username', 'like', '%'.$request->username.'%');
        }

        $data = $data->paginate($perPage);
        foreach ($data as &$value) {
            $value->roles = $value->roles()->pluck('name')->toArray();
        }

        return response()->json($data);
    }

    public function All()
    {
        $data = User::all();

        return response()->json($data);
    }

    public function Show(Request $request, $id)
    {
        $model = User::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = User::whereNot('username', 'admin')->where('_id', $id)->firstOrFail();

        $currentUser = auth()->user();
        if (($model->hasRole(Constants::ROLE_OWNER) && ! $currentUser->hasRole(Constants::ROLE_OWNER)) ||
            (! $model->hasRole(Constants::ROLE_MEMBER) && $currentUser->hasRole(Constants::ROLE_MANAGER))
        ) {
            abort(403);
        }

        $admin = User::where('username', 'admin')->firstOrFail();
        $adminId = $admin->_id;
        $alertRules = AlertRule::get();
        $modelUserEndpoints = Endpoint::where('userId', $model->_id)->get();

        foreach ($modelUserEndpoints as $modelUserEndpoint) {
            $modelUserEndpoint->userId = $adminId;
            $modelUserEndpoint->save();
        }

        foreach ($alertRules as $rule) {
            $ruleUserIds = $rule->userIds ?? [];
            $needToUpdate = false;

            if (! empty($ruleUserIds) && in_array($model->_id, $ruleUserIds)) {
                $rule->pull('userIds', $rule->_id);
                $needToUpdate = true;
            }
            if ($rule->userId == $model->_id) {
                $rule->userId = $adminId;
                $needToUpdate = true;
            }
            if ($needToUpdate) {
                $rule->save();
            }
        }

        $model->delete();

        return response()->json($model);
    }

    public function Create(Request $request)
    {

        \Validator::validate(
            $request->all(),
            [
                'username' => 'required|unique:users,username',
                'name' => 'required|string|max:255',
                'password' => 'required',
                'confirmPassword' => 'required|same:password',
                'role' => 'required|in:owner,member,manager',
            ],
        );

        if (! auth()->user()->hasRole(Constants::ROLE_OWNER) &&
            $request->post('role') == Constants::ROLE_OWNER) {
            abort(403);
        }

        $model = User::create([
            'username' => $request->post('username'),
            'name' => $request->post('name'),
            'password' => \Hash::make($request->post('password')),
        ]);

        if (auth()->user()->hasRole(Constants::ROLE_OWNER)) {
            $role = match ($request->post('role')) {
                Constants::ROLE_OWNER->value => Constants::ROLE_OWNER->value,
                Constants::ROLE_MANAGER->value => Constants::ROLE_MANAGER->value,
                default => Constants::ROLE_MEMBER,
            };
        } else {
            $role = Constants::ROLE_MEMBER->value;
        }

        $model->assignRole($role);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);

    }

    public function Update(Request $request, $id)
    {

        Validator::validate($request->all(), [
            'username' => "required|unique:users,username,{$id}",
            'name' => 'required|string|max:255',
            'role' => 'required|in:owner,member,manager',
        ]);

        $model = User::where('_id', $id)->firstOrFail();
        $currentUser = auth()->user();
        if (($model->hasRole(Constants::ROLE_OWNER) && ! $currentUser->hasRole(Constants::ROLE_OWNER)) ||
            (! $model->hasRole(Constants::ROLE_MANAGER) && $currentUser->hasRole(Constants::ROLE_MANAGER))
        ) {
            abort(403);
        }

        if ($model->username != Constants::ADMIN->value) {

            $model->update([
                'username' => $request->post('username'),
                'name' => $request->post('name'),
            ]);

            foreach ($model->roles as $role) {
                $model->removeRole($role);
            }

            if (auth()->user()->hasRole(Constants::ROLE_OWNER)) {
                $role = match ($request->post('role')) {
                    Constants::ROLE_OWNER->value => Constants::ROLE_OWNER->value,
                    Constants::ROLE_MANAGER->value => Constants::ROLE_MANAGER->value,
                    default => Constants::ROLE_MEMBER,
                };
            } else {
                $role = Constants::ROLE_MEMBER->value;
            }

            $model->syncRoles($role);
        } else {
            $model->update([
                'name' => $request->post('name'),
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);

    }

    public function ChangePassword(Request $request, $id)
    {
        Validator::validate(
            $request->all(),
            [
                'password' => 'required',
                'confirmPassword' => 'required|same:password',
            ],
        );

        $model = User::where('_id', $id)->firstOrFail();
        $currentUser = auth()->user();
        if (! $currentUser->hasRole(Constants::ROLE_OWNER) && $model->hasRole(Constants::ROLE_OWNER)) {
            abort(403);
        }

        $model->update([
            'password' => Hash::make($request->post('confirmPassword')),
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);

    }

    public function ChangeOwnerShipOfData(Request $request)
    {
        $fromUser = User::where('id', $request->fromUser)->firstOrFail();
        $toUser = User::where('id', $request->toUser)->firstOrFail();

        app(AlertRuleService::class)->ChangeOwner($fromUser, $toUser);
        app(EndpointService::class)->ChangeOwnerAll($fromUser, $toUser);

        return response()->json([
            'status' => true,
        ]);

    }
}
