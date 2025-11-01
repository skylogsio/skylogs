<?php

namespace App\Http\Controllers\V1;

use App\Enums\EndpointType;
use App\Http\Controllers\Controller;
use App\Models\Endpoint;
use App\Models\User;
use App\Services\EndpointService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EndpointController extends Controller
{
    public function __construct(protected EndpointService $endpointService) {}

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
        $isAdmin = auth()->user()->isAdmin();
        if (! $isAdmin) {
            $data = $data->where('userId', auth()->id());
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
        $isAdmin = auth()->user()->isAdmin();
        if (! $isAdmin) {
            $data = $data->where('userId', auth()->id());
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

        $va = \Validator::make(
            $request->all(),
            [
                'type' => [
                    'required',
                    Rule::in([
                        'email',
                        'sms',
                        'call',
                    ]),
                ],
                'value' => 'required',
            ],
        );

        if ($va->fails()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid Entry');
        }

        $endpointOtp = $this->endpointService->otpRequest($request);

        return response()->json([
            'message' => 'OTP code has been sent to your endpoint',
            "expiredAt" => $endpointOtp->expiredAt->getTimestamp(),
            "timeLeft" => intval(Carbon::now()->diffInSeconds($endpointOtp->expiredAt)),
        ]);
    }

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
