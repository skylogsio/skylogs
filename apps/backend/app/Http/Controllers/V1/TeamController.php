<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function Index(Request $request)
    {

        $perPage = $request->perPage ?? 25;

        $data = Team::query();

        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }

        $data = $data->paginate($perPage);

        return response()->json($data);

    }

    public function Show($id)
    {

        return response()->json(Team::findOrFail($id));

    }

    public function Create(Request $request)
    {

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required|unique:teams',
                'ownerId' => 'required',
                'userIds' => 'required|array',
            ],
        );

        if ($va->passes()) {

            $team = Team::create([
                'name' => $request->name,
                'ownerId' => $request->ownerId,
                'userIds' => $request->userIds,
                'description' => $request->description ?? '',
            ]);

            $team->save();

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
                'name' => 'required|unique:teams',
                'ownerId' => 'required',
                'userIds' => 'required|array',
            ],
        );

        if ($va->passes()) {

            $team = Team::where('id', $id)->firstOrFail();

            $team->name = $request->name;
            $team->ownerId = $request->ownerId;
            $team->userIds = $request->userIds;
            $team->description = $request->description ?? '';
            $team->save();

            return ['status' => true];
        } else {
            return ['status' => false];
        }
    }

    public function Delete(Request $request, $id)
    {

        $model = Team::where('_id', $id)->firstOrFail();

        $model->delete();

        return ['status' => true];
    }
}
