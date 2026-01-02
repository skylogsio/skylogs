<?php

namespace App\Http\Controllers\V1\Config;

use App\Enums\CallProviderType;
use App\Http\Controllers\Controller;
use App\Models\Config\ConfigCall;
use App\Services\ConfigCallService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $data = ConfigCall::query()->orderByDesc('isDefault')->orderByDesc('isBackup')->latest();
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }
        $data = $data->get();

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
        $model->delete();

        return response()->json($model);
    }

    public function Create(Request $request)
    {
        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'provider' => ['required', Rule::enum(CallProviderType::class)],
                'apiToken' => 'required_if:provider,'.CallProviderType::KAVE_NEGAR->value,
            ],
        );
        if ($va->passes()) {

            if ($request->provider == CallProviderType::KAVE_NEGAR->value) {

                $isDefault = false;
                if (ConfigCall::where('isDefault', true)->count() == 0) {
                    $isDefault = true;
                }

                $modelArray = [
                    'name' => $request->name,
                    'provider' => $request->provider,
                    'apiToken' => $request->apiToken,
                    'isDefault' => $isDefault,
                    'isBackUp' => false,
                ];

                $model = ConfigCall::create($modelArray);

                return response()->json([
                    'status' => true,
                    'data' => $model,
                ]);
            }
        }

        return response()->json([
            'status' => false,
        ]);

    }

    public function Update(Request $request, $id)
    {
        $model = ConfigCall::where('_id', $id);
        $model = $model->firstOrFail();

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'provider' => ['required', Rule::enum(CallProviderType::class)],
                'apiToken' => 'required_if:provider,'.CallProviderType::KAVE_NEGAR->value,
            ],
        );

        if ($va->passes()) {
            if ($request->provider == CallProviderType::KAVE_NEGAR->value) {

                $modelArray = [
                    'name' => $request->name,
                    'provider' => $request->provider,
                    'apiToken' => $request->apiToken,
                ];

                $model->update($modelArray);

                return response()->json([
                    'status' => true,
                    'data' => $model,
                ]);
            }

        }

        return response()->json([
            'status' => false,
        ]);
    }

    public function makeDefault($id)
    {
        $model = ConfigCall::where('id', $id)->firstOrFail();
        $this->configService->makeDefault($model);

        return response()->json([
            'status' => true,
            'data' => $model,
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
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }
}
