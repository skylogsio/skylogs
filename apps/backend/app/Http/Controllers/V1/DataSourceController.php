<?php

namespace App\Http\Controllers\V1;

use App\Enums\DataSourceType;
use App\Http\Controllers\Controller;
use App\Models\DataSource\DataSource;
use App\Services\DataSourceService;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function __construct(protected DataSourceService $dataSourceService) {}

    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = DataSource::latest();
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }
        $data = $data->paginate($perPage);

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = DataSource::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = DataSource::where('_id', $id);
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
                'type' => 'required',
                'url' => 'required',
            ],
        );
        if ($va->passes()) {

            $url = rtrim($request->url, '/');

            do {
                $webhookToken = \Str::random(5);
            } while (DataSource::where('webhookToken', $webhookToken)->first());

            $modelArray = [
                'type' => $request->type,
                'name' => $request->name,
                'url' => $url,
                'webhookToken' => $webhookToken,
            ];
            $modelArray['api_token'] = $request->api_token ?? '';
            $modelArray['apiToken'] = $request->api_token ?? '';
            $modelArray['username'] = $request->username ?? '';
            $modelArray['password'] = $request->password ?? '';
            $model = DataSource::create($modelArray);

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

    public function Update(Request $request, $id)
    {
        $model = DataSource::where('_id', $id);
        $model = $model->firstOrFail();

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'type' => 'required',
                'url' => 'required',
            ],
        );

        if ($va->passes()) {
            $url = rtrim($request->url, '/');
            $modelArray = [
                'type' => $request->type,
                'name' => $request->name,
                'url' => $url,
            ];
            $modelArray['api_token'] = $request->api_token ?? '';
            $modelArray['apiToken'] = $request->api_token ?? '';
            $modelArray['username'] = $request->username ?? '';
            $modelArray['password'] = $request->password ?? '';

            $model->update($modelArray);

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

    public function GetTypes(Request $request)
    {
        return response()->json(DataSourceType::GetTypes());
    }

    public function IsConnected($id)
    {
        $isConnected = $this->dataSourceService->isConnected($id);

        return response()->json(['isConnected' => $isConnected]);
    }
}
