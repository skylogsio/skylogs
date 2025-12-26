<?php

namespace App\Http\Controllers\V1\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\ConfigEmail;
use App\Services\ConfigEmailService;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function __construct(protected ConfigEmailService $configService) {}

    public function Index(Request $request)
    {

        $data = ConfigEmail::query()->orderByDesc('isDefault')->orderByDesc('isBackup')->latest();
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }
        $data = $data->get();

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = ConfigEmail::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = ConfigEmail::where('_id', $id);
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
                'host' => 'required',
                'port' => 'required',
                'username' => 'required',
                'password' => 'required',
                'fromAddress' => 'required',
            ],
        );
        if ($va->passes()) {

            $isDefault = false;
            if (ConfigEmail::where('isDefault', true)->count() == 0) {
                $isDefault = true;
            }

            $modelArray = [
                'name' => $request->name,
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username,
                'password' => $request->password,
                'fromAddress' => $request->fromAddress,
                'isDefault' => $isDefault,
                'isBackUp' => false,
            ];

            $model = ConfigEmail::create($modelArray);

            return response()->json([
                'status' => true,
                'data' => $model,
            ]);

        }

        return response()->json([
            'status' => false,
        ]);

    }

    public function Update(Request $request, $id)
    {
        $model = ConfigEmail::where('_id', $id);
        $model = $model->firstOrFail();

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'host' => 'required',
                'port' => 'required',
                'username' => 'required',
                'password' => 'required',
                'fromAddress' => 'required',
            ],
        );

        if ($va->passes()) {

            $modelArray = [
                'name' => $request->name,
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username,
                'password' => $request->password,
                'fromAddress' => $request->fromAddress,
            ];

            $model->update($modelArray);

            return response()->json([
                'status' => true,
                'data' => $model,
            ]);

        }

        return response()->json([
            'status' => false,
        ]);
    }

    public function makeDefault($id)
    {
        $model = ConfigEmail::where('id', $id)->firstOrFail();
        $this->configService->makeDefault($model);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function makeBackup($id)
    {
        $model = ConfigEmail::where('id', $id)->firstOrFail();
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
