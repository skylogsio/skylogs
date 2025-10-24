<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\SkylogsInstance;
use App\Services\AlertRuleService;
use App\Services\ClusterService;
use App\Services\SkylogsInstanceService;
use Illuminate\Http\Request;

class SkylogsInstanceController extends Controller
{
    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = SkylogsInstance::query();

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $data = $data->paginate($perPage);

        return response()->json($data);
    }

    public function Show(Request $request, $id)
    {
        $model = SkylogsInstance::where('id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = SkylogsInstance::where('_id', $id);
        $model = $model->firstOrFail();
        $model->delete();
        app(AlertRuleService::class)->deleteHealthCluster($model);

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
                $token = \Str::random(32);
            } while (SkylogsInstance::where('token', $token)->first());

            $model = SkylogsInstance::create([
                'name' => $request->name,
                'type' => $request->type,
                'url' => $url,
                'token' => $token,
            ]);

            app(ClusterService::class)->refreshHealthAgent($model);

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
        $model = SkylogsInstance::where('_id', $id);
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

            $model->update([
                'name' => $request->name,
                'type' => $request->type,
                'url' => $request->url,
            ]);
            app(ClusterService::class)->refreshHealthAgent($model);

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

    public function IsConnected($id)
    {
        $isConnected = app(SkylogsInstanceService::class)->isConnected($id);

        return response()->json(['isConnected' => $isConnected]);
    }
}
