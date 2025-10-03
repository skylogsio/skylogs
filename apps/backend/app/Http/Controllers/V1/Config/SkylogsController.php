<?php

namespace App\Http\Controllers\V1\Config;
;


use App\Enums\ClusterType;
use App\Http\Controllers\Controller;
use App\Models\Config\ConfigSkylogs;
use App\Models\Config\ConfigTelegram;
use App\Services\ClusterService;
use App\Services\ConfigSkylogsService;
use App\Services\ConfigTelegramService;
use Illuminate\Http\Request;
use Validator;


class SkylogsController extends Controller
{


    public function __construct(protected ConfigSkylogsService $configService)
    {
    }

    public function ClusterType(Request $request)
    {
        $config = $this->configService->cluster();
        if ($config) {
            return response()->json($config);
        } else {
            return response()->json([
                "name" => "cluster",
                "type" => ClusterType::MAIN,
                "sourceUrl" => "",
                "sourceToken" => "",
            ]);
        }
    }

    public function StoreClusterType(Request $request)
    {
        $validate = Validator::make($request->all(), [
            "type" => "required|string|in:" . ClusterType::MAIN->value . "," . ClusterType::AGENT->value,
            "sourceUrl" => "required_if:type," . ClusterType::AGENT->value."|url:http,https",
            "sourceToken" => "required_if:type," . ClusterType::AGENT->value,
        ]);

        if ($validate->fails()) {
            return response()->json($validate->errors(), 422);
        }

        $model = ConfigSkylogs::where('name', "cluster")->firstOrNew([
            "name" => "cluster",
        ]);

        $clusterType = ClusterType::from($request->type);
        $model->type = $clusterType;
        if ($model->type == ClusterType::AGENT) {
            $model->sourceUrl = $request->sourceUrl;
            $model->sourceToken = $request->sourceToken;
        } else {
            $model->sourceUrl = "";
            $model->sourceToken = "";
        }

        $model->save();
        app(ClusterService::class)->refreshHealthMain($model);

        return response()->json($model);
    }

}
