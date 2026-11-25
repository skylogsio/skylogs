<?php

namespace App\Http\Controllers\V1\Config;

use App\Enums\CallProviderType;
use App\Enums\EndpointType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Config\SetConfigCallPrioritiesRequest;
use App\Http\Requests\Config\StoreConfigCallRequest;
use App\Http\Requests\Config\UpdateConfigCallRequest;
use App\Models\Config\ConfigCall;
use App\Services\ConfigCallService;
use App\Services\Notification\NotificationProviderRegistry;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(protected ConfigCallService $configService) {}

    public function providers()
    {

        $providers = CallProviderType::GetList();
        $result = [];
        foreach ($providers as $key => $provider) {
            $result[] = [
                'key' => $key,
                'value' => $provider,
            ];
        }

        return response()->json($result);
    }

    public function Index(Request $request)
    {

        $data = ConfigCall::query()
            ->orderByDesc('isDefault')
            ->orderByDesc('isBackUp')
            ->orderBy('priority')
            ->latest()
            ->get();

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = ConfigCall::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = ConfigCall::where('_id', $id);
        $model = $model->firstOrFail();
        $isDefault = $model->isDefault;

        if ($isDefault) {
            $count = ConfigCall::all()->count();
            if ($count != 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Default can not be deleted',
                ], 422);
            }
        }

        $model->delete();

        return response()->json($model);
    }

    public function Create(StoreConfigCallRequest $request)
    {
        $provider = app(NotificationProviderRegistry::class)->for($request->validated('provider'));
        $isDefault = ConfigCall::query()->where('isDefault', true)->doesntExist();

        $model = ConfigCall::create([
            'name' => $request->validated('name'),
            'provider' => $request->validated('provider'),
            ...$provider->attributes(EndpointType::CALL, $request->validated()),
            'isDefault' => $isDefault,
            'isBackUp' => false,
            'priority' => $isDefault ? 1 : null,
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function Update(UpdateConfigCallRequest $request, $id)
    {
        $model = ConfigCall::where('_id', $id)->firstOrFail();
        $provider = app(NotificationProviderRegistry::class)->for($request->validated('provider'));

        $model->update([
            'name' => $request->validated('name'),
            'provider' => $request->validated('provider'),
            ...$provider->attributes(EndpointType::CALL, $request->validated()),
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function priorities(SetConfigCallPrioritiesRequest $request)
    {
        $this->configService->setPriorities($request->validated('ids'));

        return response()->json([
            'status' => true,
            'data' => ConfigCall::query()
                ->orderByDesc('isDefault')
                ->orderByDesc('isBackUp')
                ->orderBy('priority')
                ->latest()
                ->get(),
        ]);
    }

    public function makeDefault($id)
    {
        $model = ConfigCall::where('id', $id)->firstOrFail();
        $this->configService->makeDefault($model);

        return response()->json([
            'status' => true,
            'data' => $model->refresh(),
        ]);
    }

    public function makeBackup($id)
    {
        $model = ConfigCall::where('id', $id)->firstOrFail();
        try {
            $this->configService->makeBackUp($model);
        } catch (\Exception $exception) {

            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'data' => $model->refresh(),
        ]);
    }
}
