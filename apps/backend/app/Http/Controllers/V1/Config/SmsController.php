<?php

namespace App\Http\Controllers\V1\Config;

use App\Enums\EndpointType;
use App\Enums\SmsProviderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Config\SetConfigSmsPrioritiesRequest;
use App\Http\Requests\Config\StoreConfigSmsRequest;
use App\Http\Requests\Config\UpdateConfigSmsRequest;
use App\Models\Config\ConfigSms;
use App\Services\ConfigSmsService;
use App\Services\Notification\NotificationProviderRegistry;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(protected ConfigSmsService $configService) {}

    public function providers()
    {

        $providers = SmsProviderType::GetList();
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

        $data = ConfigSms::query()
            ->orderByDesc('isDefault')
            ->orderByDesc('isBackUp')
            ->orderBy('priority')
            ->latest()
            ->get();

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = ConfigSms::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = ConfigSms::where('_id', $id);
        $model = $model->firstOrFail();
        $isDefault = $model->isDefault;

        if ($isDefault) {
            $count = ConfigSms::all()->count();
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

    public function Create(StoreConfigSmsRequest $request)
    {
        $provider = app(NotificationProviderRegistry::class)->for($request->validated('provider'));
        $isDefault = ConfigSms::query()->where('isDefault', true)->doesntExist();

        $model = ConfigSms::create([
            'name' => $request->validated('name'),
            'provider' => $request->validated('provider'),
            ...$provider->attributes(EndpointType::SMS, $request->validated()),
            'isDefault' => $isDefault,
            'isBackUp' => false,
            'priority' => $isDefault ? 1 : null,
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function Update(UpdateConfigSmsRequest $request, $id)
    {
        $model = ConfigSms::where('_id', $id)->firstOrFail();
        $provider = app(NotificationProviderRegistry::class)->for($request->validated('provider'));

        $model->update([
            'name' => $request->validated('name'),
            'provider' => $request->validated('provider'),
            ...$provider->attributes(EndpointType::SMS, $request->validated()),
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function priorities(SetConfigSmsPrioritiesRequest $request)
    {
        $this->configService->setPriorities($request->validated('ids'));

        return response()->json([
            'status' => true,
            'data' => ConfigSms::query()
                ->orderByDesc('isDefault')
                ->orderByDesc('isBackUp')
                ->orderBy('priority')
                ->latest()
                ->get(),
        ]);
    }

    public function makeDefault($id)
    {
        $model = ConfigSms::where('id', $id)->firstOrFail();
        $this->configService->makeDefault($model);

        return response()->json([
            'status' => true,
            'data' => $model->refresh(),
        ]);
    }

    public function makeBackup($id)
    {
        $model = ConfigSms::where('id', $id)->firstOrFail();
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
