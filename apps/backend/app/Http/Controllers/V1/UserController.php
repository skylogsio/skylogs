<?php

namespace App\Http\Controllers\V1;

use App\Enums\Constants;
use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\AlertRuleService;
use App\Services\EndpointService;
use App\Services\UserService;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

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
        $model = User::where('_id', $id)->firstOrFail();
        $model->roles = $model->roles()->pluck('name')->toArray();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = User::whereNot('username', 'admin')->where('_id', $id)->firstOrFail();

        $currentUser = auth()->user();
        if (! $this->users->canManageUser($currentUser, $model)) {
            abort(403);
        }

        $admin = User::where('username', 'admin')->firstOrFail();
        $adminId = (string) $admin->id;
        $deletedUserId = (string) $model->id;
        $alertRules = AlertRule::get();
        $modelUserEndpoints = Endpoint::query()
            ->where('userId', $deletedUserId)
            ->get();

        foreach ($modelUserEndpoints as $modelUserEndpoint) {
            $modelUserEndpoint->userId = $adminId;
            $modelUserEndpoint->save();
        }

        foreach ($alertRules as $rule) {
            $ruleUserIds = $rule->userIds ?? [];
            $needToUpdate = false;

            foreach ($ruleUserIds as $ruleUserId) {
                if ((string) $ruleUserId !== $deletedUserId) {
                    continue;
                }

                $rule->pull('userIds', $ruleUserId);
                $needToUpdate = true;
            }
            if ((string) $rule->userId === $deletedUserId) {
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

        Validator::validate(
            $request->all(),
            [
                'username' => 'required|unique:users,username',
                'name' => 'required|string|max:255',
                'password' => 'required',
                'confirmPassword' => 'required|same:password',
                'role' => 'required|in:owner,member,manager',
            ],
        );

        $actor = auth()->user();
        $requestedRole = (string) $request->post('role');

        if (! $actor->hasRole(Constants::ROLE_OWNER) && $requestedRole !== Constants::ROLE_MEMBER->value) {
            abort(403);
        }

        $model = User::create([
            'username' => $request->post('username'),
            'name' => $request->post('name'),
            'password' => Hash::make($request->post('password')),
        ]);

        $model->assignRole($this->assignableRole($actor, $requestedRole));

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);

    }

    public function Update(Request $request, $id)
    {

        Validator::validate($request->all(), [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id, '_id')],
            'name' => 'required|string|max:255',
            'role' => 'required|in:owner,member,manager',
        ]);

        $model = User::where('_id', $id)->firstOrFail();
        $currentUser = auth()->user();
        if (! $this->users->canManageUser($currentUser, $model)) {
            abort(403);
        }

        $requestedRole = (string) $request->post('role');

        if (! $currentUser->hasRole(Constants::ROLE_OWNER) && $requestedRole !== Constants::ROLE_MEMBER->value) {
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

            $model->syncRoles($this->assignableRole($currentUser, $requestedRole));
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
        if (! $this->users->canManageUser($currentUser, $model)) {
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

    private function assignableRole(User $actor, string $requestedRole): string
    {
        if (! $actor->hasRole(Constants::ROLE_OWNER)) {
            return Constants::ROLE_MEMBER->value;
        }

        return match ($requestedRole) {
            Constants::ROLE_OWNER->value => Constants::ROLE_OWNER->value,
            Constants::ROLE_MANAGER->value => Constants::ROLE_MANAGER->value,
            default => Constants::ROLE_MEMBER->value,
        };
    }
}
