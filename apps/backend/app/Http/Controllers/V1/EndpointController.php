<?php

namespace App\Http\Controllers\V1;

use App\Enums\EndpointType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Endpoint\SendEndpointOtpRequest;
use App\Http\Requests\Endpoint\StoreEndpointRequest;
use App\Http\Requests\Endpoint\UpdateEndpointRequest;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\EndpointService;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EndpointController extends Controller
{
    public function __construct(protected EndpointService $endpointService, protected TeamService $teamService) {}

    public function EndpointsToCreateFlow()
    {
        $endpoints = $this->endpointService->selectableUserEndpoint(\Auth::user());
        $endpoints = $endpoints->where('type', '!=', EndpointType::FLOW->value)->values();

        return response()->json($endpoints);
    }

    public function SelectableEndpoints()
    {
        $endpoints = $this->endpointService->selectableUserEndpoint(\Auth::user())->values();

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

        foreach ($data as &$endpoint) {
            $endpoint->hasActionAccess = $this->endpointService->hasActionAccess($user, $endpoint);
        }

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

        foreach ($data as &$endpoint) {
            $endpoint->hasActionAccess = $this->endpointService->hasActionAccess($user, $endpoint);
        }

        $this->endpointService->attachFlowStepEndpoints($data->getCollection());

        return response()->json($data);
    }

    public function Show(Request $request, $id)
    {
        $user = auth()->user();
        $model = Endpoint::where('_id', $id)->firstOrFail();

        abort_unless($this->endpointService->userCanUseEndpoint($user, $model), 404);

        $model->botToken = $model->botToken ?? '';
        $model->hasActionAccess = $this->endpointService->hasActionAccess($user, $model);

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

    public function Create(StoreEndpointRequest $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->endpointService->create($request->validated()),
        ]);
    }

    public function SendOTPCode(SendEndpointOtpRequest $request): JsonResponse
    {
        return response()->json($this->endpointService->otpRequest($request->validated()));
    }

    public function Update(UpdateEndpointRequest $request, $id): JsonResponse
    {
        $model = Endpoint::where('_id', $id);
        $isAdmin = auth()->user()->isAdmin();
        if (! $isAdmin) {
            $model = $model->where('userId', auth()->id());
        }
        $model = $model->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $this->endpointService->update($model, $request->validated()),
        ]);
    }

    public function ChangeOwner(Request $request, $id)
    {
        $endpoint = Endpoint::where('_id', $id);
        $isAdmin = auth()->user()->isAdmin();

        if (! $isAdmin) {
            $endpoint = $endpoint->where('userId', auth()->id());
        }

        $endpoint = $endpoint->firstOrFail();

        $toUser = User::where('id', $request->userId)->firstOrFail();

        $endpoint->userId = $toUser->id;
        $endpoint->save();

        return response()->json([
            'status' => true,
            'message' => 'Successfully change owner',
        ]);

    }
}
