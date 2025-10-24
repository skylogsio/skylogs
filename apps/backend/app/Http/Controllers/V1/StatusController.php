<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\Status;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function Index(Request $request)
    {

        $perPage = $request->perPage ?? 25;

        $data = Status::query();

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $data = $data->paginate($perPage);

        return response()->json($data);

    }

    public function Show($id)
    {

        return response()->json(Status::findOrFail($id));

    }

    public function Status(Request $request)
    {

        $result = Status::all();

        return response()->json($result);

    }

    public function Create(Request $request)
    {

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required|unique:status',
                'tags' => 'required|array',
            ],
        );

        if ($va->passes() && count($request->tags) != 0) {
            $tags = collect($request->tags)->map(fn ($item) => trim($item))->unique()->toArray();

            $status = Status::create([
                'name' => $request->name,
                'tags' => $tags,
                'criticalCount' => 0,
                'warningCount' => 0,
                'status' => AlertRule::RESOlVED,
            ]);

            $status->save();

            return ['status' => true];
        } else {
            return ['status' => false];
        }
    }

    public function Update(Request $request, $id)
    {

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required|unique:status',
                'tags' => 'required|array',
            ],
        );

        if ($va->passes() && count($request->tags) != 0) {
            $tags = collect($request->tags)->map(fn ($item) => trim($item))->unique()->toArray();

            $status = Status::where('id', $id)->firstOrFail();

            $status->name = $request->name;
            $status->tags = $tags;
            $status->save();

            return ['status' => true];
        } else {
            return ['status' => false];
        }
    }

    public function Delete(Request $request, $id)
    {

        $model = Status::where('_id', $id)->firstOrFail();

        $model->delete();

        return ['status' => true];
    }
}
