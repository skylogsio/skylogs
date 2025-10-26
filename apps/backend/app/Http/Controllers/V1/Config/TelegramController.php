<?php

namespace App\Http\Controllers\V1\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\ConfigTelegram;
use App\Services\ConfigTelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(protected ConfigTelegramService $configService) {}

    public function Index(Request $request)
    {

        $data = ConfigTelegram::query()->orderByDesc('active')->latest();
        if ($request->filled('name')) {
            $data->where('name', 'like', '%'.$request->name.'%');
        }
        $data = $data->get();

        return response()->json($data);

    }

    public function Show(Request $request, $id)
    {
        $model = ConfigTelegram::where('_id', $id);
        $model = $model->firstOrFail();

        return response()->json($model);
    }

    public function Delete(Request $request, $id)
    {
        $model = ConfigTelegram::where('_id', $id);
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
                'type' => 'required|in:http,socks5',
                'host' => 'required',
                'port' => 'required',
            ],
        );
        if ($va->passes()) {

            $modelArray = [
                'name' => $request->name,
                'type' => $request->type,
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username ?? '',
                'password' => $request->password ?? '',
                'active' => false,
            ];

            $model = ConfigTelegram::create($modelArray);

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
        $model = ConfigTelegram::where('_id', $id);
        $model = $model->firstOrFail();

        $va = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'type' => 'required|in:http,socks5',
                'host' => 'required',
                'port' => 'required',
            ],
        );

        if ($va->passes()) {

            $modelArray = [
                'name' => $request->name,
                'type' => $request->type,
                'host' => $request->host,
                'port' => $request->port,
                'username' => $request->username ?? '',
                'password' => $request->password ?? '',
                'active' => false,
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

    public function Activate($id)
    {
        $model = ConfigTelegram::where('id', $id)->firstOrFail();
        $this->configService->activate($model);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function Deactivate()
    {
        $this->configService->deactivate();

        return response()->json([
            'status' => true,
        ]);
    }
}
