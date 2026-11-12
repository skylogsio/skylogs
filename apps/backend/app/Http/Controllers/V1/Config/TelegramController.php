<?php

namespace App\Http\Controllers\V1\Config;

use App\Http\Controllers\Controller;
use App\Http\Requests\Config\ConfigTelegramRequest;
use App\Http\Requests\Config\SetConfigTelegramPrioritiesRequest;
use App\Models\Config\ConfigTelegram;
use App\Services\ConfigTelegramService;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function __construct(protected ConfigTelegramService $configService) {}

    public function Index(Request $request)
    {

        $data = ConfigTelegram::query()->orderByDesc('active')->orderBy('priority')->latest();
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

    public function Create(ConfigTelegramRequest $request)
    {
        $direct = $request->validated('type') === 'direct';

        $model = ConfigTelegram::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'host' => $direct ? null : $request->validated('host'),
            'port' => $direct ? null : $request->validated('port'),
            'username' => $request->validated('username') ?? '',
            'password' => $request->validated('password') ?? '',
            'active' => false,
            'priority' => null,
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function Update(ConfigTelegramRequest $request, $id)
    {
        $model = ConfigTelegram::where('_id', $id)->firstOrFail();
        $direct = $request->validated('type') === 'direct';

        $model->update([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'host' => $direct ? null : $request->validated('host'),
            'port' => $direct ? null : $request->validated('port'),
            'username' => $request->validated('username') ?? '',
            'password' => $request->validated('password') ?? '',
        ]);

        return response()->json([
            'status' => true,
            'data' => $model,
        ]);
    }

    public function priorities(SetConfigTelegramPrioritiesRequest $request)
    {
        $this->configService->setPriorities($request->validated('ids'));

        return response()->json([
            'status' => true,
            'data' => ConfigTelegram::query()->orderByDesc('active')->orderBy('priority')->latest()->get(),
        ]);
    }

    public function Activate($id)
    {
        $model = ConfigTelegram::where('id', $id)->firstOrFail();
        $this->configService->activate($model);

        return response()->json([
            'status' => true,
            'data' => $model->refresh(),
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
