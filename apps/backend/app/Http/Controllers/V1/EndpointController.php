<?php

namespace App\Http\Controllers\V1;

use App\Enums\EndpointType;
use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Models\User;
use App\Services\EndpointService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EndpointController extends Controller
{
    public function __construct(protected EndpointService $endpointService, protected TeamService $teamService) {}

    public function EndpointsToCreateFlow()
    {
        $endpoints = $this->endpointService->selectableUserEndpoint(\Auth::user());
        $endpoints = $endpoints->where('type', '!=', EndpointType::FLOW->value)->values();

        return response()->json($endpoints);
    }

    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = Endpoint::query()->whereNot('type', EndpointType::FLOW->value);
        $user = auth()->user();

        if (! $user->isAdmin()) {
            $data = $data->where(function ($query) use ($user) {
                $teamIds = $this->teamService->userTeams($user)->pluck('id')->toArray();

                return $query->where('userId', $user->id)
                    ->orWhereIn('accessUserIds', [$user->id])
                    ->orWhereIn('accessTeamIds', $teamIds);
            });
        }

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $data = $data->paginate($perPage);

        return response()->json($data);
    }

    public function IndexFlow(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = Endpoint::query()->where('type', EndpointType::FLOW->value);
        $user = auth()->user();

        if (! $user->isAdmin()) {
            $data = $data->where(function ($query) use ($user) {
                $teamIds = $this->teamService->userTeams($user)->pluck('id')->toArray();

                return $query->where('userId', $user->id)
                    ->orWhereIn('accessUserIds', [$user->id])
                    ->orWhereIn('accessTeamIds', $teamIds);
            });
        }
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $data = $data->paginate($perPage);

        return response()->json($data);
    }

    public function Show(Request $request, $id)
    {
        $model = Endpoint::where('id', $id);
        $isAdmin = auth()->user()->isAdmin();
        if (! $isAdmin) {
            $model = $model->where('userId', auth()->id());
        }
        $model = $model->firstOrFail();

        $model->botToken = $model->botToken ?? '';

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = Endpoint::where('_id', $id);
        $isAdmin = auth()->user()->isAdmin();

        if (! $isAdmin) {
            $model = $model->where('userId', auth()->id());
        }
        $model = $model->firstOrFail();
        $model->delete();

        return response()->json($model);
    }

    public function Create(Request $request)
    {
        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'type' => [
                    'required',
                    Rule::in([
                        'telegram',
                        'email',
                        'sms',
                        'flow',
                        'call',
                        'discord',
                        'teams',
                        'matter-most',
                    ]),
                ],
            ],
        );
        if ($va->passes()) {
            $model = $this->endpointService->create($request);

            return response()->json([
                'status' => true,
                'data' => $model,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function SendOTPCode(Request $request)
    {

        EndpointOTP::updateOrCreate([
            'type' => $request->type,
            'value' => $request->value,
        ], [

        ]);
    }

    public function ConfirmOTPCode(Request $request) {}

    public function Update(Request $request, $id)
    {
        $model = Endpoint::where('_id', $id);
        $isAdmin = auth()->user()->isAdmin();
        if (! $isAdmin) {
            $model = $model->where('userId', auth()->id());
        }
        $model = $model->firstOrFail();

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'type' => [
                    'required',
                    Rule::in([
                        'telegram',
                        'email',
                        'sms',
                        'discord',
                        'call',
                        'flow',
                        'teams',
                        'matter-most',
                    ]),
                ],
            ],
        );
        if ($va->passes()) {
            $this->endpointService->update($model, $request);

            return response()->json([
                'status' => true,
                'data' => $model,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function ChangeOwner(Request $request, $id)
    {
        $endpoint = Endpoint::where('_id', $id);
        $isAdmin = auth()->user()->isAdmin();

        if (! $isAdmin) {
            $endpoint = $endpoint->where('userId', auth()->id());
        }

        $endpoint = $endpoint->firstOrFail();

        $toUser = User::where('id', $request->user_id)->firstOrFail();

        $endpoint->userId = $toUser->id;
        $endpoint->save();

        return response()->json([
            'status' => true,
            'message' => 'Successfully change owner',
        ]);

    }
}
