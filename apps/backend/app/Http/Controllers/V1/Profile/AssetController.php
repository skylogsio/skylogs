<?php

namespace App\Http\Controllers\V1\Profile;

use App\Http\Controllers\Controller;
use App\Models\Profile\ProfileAsset;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(protected ProfileService $profileService) {}

    public function Index(Request $request)
    {
        $perPage = $request->perPage ?? 25;

        $data = ProfileAsset::latest()->with('user');
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }
        $data = $data->paginate($perPage);

        return response()->json($data);

    }

    public function Show($id)
    {
        $model = ProfileAsset::where('_id', $id)->with('user');
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete($id)
    {
        $model = ProfileAsset::where('_id', $id);
        $model = $model->firstOrFail();
        $model->delete();
        $this->profileService->delete($model);

        return response()->json($model);
    }

    public function Create(Request $request)
    {
        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'ownerId' => 'required',
                'config' => 'required',
            ],
        );
        if ($va->passes()) {

            $modelArray = [
                'name' => $request->name,
                'ownerId' => $request->ownerId,
                'config' => $request->config,
                'createdAlertRuleIds' => [],
            ];
            $model = ProfileAsset::create($modelArray);
            $createdAlerts = $this->profileService->createAlertRules($model);
            $model->createdAlertRuleIds = $createdAlerts->pluck('id')->toArray();
            $model->save();

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
        $model = ProfileAsset::where('_id', $id);
        $model = $model->firstOrFail();
        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'ownerId' => 'required',
                'config' => 'required',
            ],
        );

        if ($va->passes()) {
            $modelArray = [
                'name' => $request->name,
                'ownerId' => $request->ownerId,
                'config' => $request->config,
            ];

            $model->update($modelArray);
            $model->createdAlertRuleIds = $this->profileService->createAlertRules($model);
            $model->save();

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
