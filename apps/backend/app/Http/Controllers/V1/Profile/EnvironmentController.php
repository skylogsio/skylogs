<?php

namespace App\Http\Controllers\V1\Profile;


use App\Http\Controllers\Controller;
use App\Models\Profile\ProfileEnvironment;
use App\Models\DataSource\DataSource;
use App\Models\Service;
use App\Services\DataSourceService;
use Illuminate\Http\Request;


class EnvironmentController extends Controller
{


    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = ProfileEnvironment::latest();
        if ($request->filled('name')) {
            $data->where('name', 'like', '%' . $request->name . '%');
        }
        $data = $data->paginate($perPage);

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = ProfileEnvironment::where('_id', $id);
        $model = $model->firstOrFail();
        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = ProfileEnvironment::where('_id', $id);
        $model = $model->firstOrFail();
        $model->delete();
        return response()->json($model);
    }

    public function Create(Request $request)
    {
        $va = \Validator::make(
            $request->all(),
            [
                'name' => "required",
            ],
        );
        if ($va->passes()) {

            $modelArray = [
                "name" => $request->name,
            ];
            $model = ProfileEnvironment::create($modelArray);
            return response()->json([
                'status' => true,
                "data" => $model
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    public function Update(Request $request, $id)
    {
        $model = ProfileEnvironment::where('_id', $id);
        $model = $model->firstOrFail();


        $va = \Validator::make(
            $request->all(),
            [
                'name' => "required",
            ],
        );

        if ($va->passes()) {
            $modelArray = [
                "name" => $request->name,
            ];
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


}
