<?php

namespace App\Helpers;

use App\Enums\CallProviderType;
use App\interfaces\Messageable;
use App\Models\EndpointOTP;
use App\Services\Config\Call\CallKaveNegarService;
use App\Services\ConfigCallService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Call
{
    private static function Url()
    {

        return 'https://api.kavenegar.com/v1'.'/'.self::Token().'/call/maketts.json';
    }

    private static function Token()
    {

        return config('variables.kavenegarToken');
    }

    public static function sendAlert($nums, Messageable $alert)
    {

        if (empty($nums)) {
            return '';
        }

        $config = app(ConfigCallService::class)->getDefault();

        if (! empty($config)) {
            if ($config->provider == CallProviderType::KAVE_NEGAR->value) {
                return app(CallKaveNegarService::class)->sendAlert($config, $nums, $alert);
            }

            return "$config->provider is not providing";
        }

        if (empty(self::Token()))
            return "Sms is not configured";

        $result = Http::pool(function (Pool $pool) use ($nums, $alert) {
            if ($nums instanceof Collection) {
                $numsString = $nums->implode(',');
            } else {
                $numsString = implode(',', $nums);
            }

            return $pool->get(self::Url(), [
                'message' => $alert->callMessage(),
                'receptor' => $numsString,
            ]);
        });

        $resultJson = [];
        foreach ($result as $item) {

            try {
                if ($item instanceof Response) {
                    $resultJson[] = $item->json();
                } else {
                    $resultJson[] = $item->getMessage();
                }

            } catch (\Exception $e) {
                $resultJson[] = $e->getMessage();
            }
        }

        return $resultJson;

    }

    public static function sendOTP(EndpointOTP $endpoint)
    {


        $config = app(ConfigCallService::class)->getDefault();

        if (! empty($config)) {
            if ($config->provider == CallProviderType::KAVE_NEGAR->value) {
                return app(CallKaveNegarService::class)->sendOTP($config, $endpoint);
            }

            return "$config->provider is not providing";
        }

        if (empty(self::Token()))
            return "Sms is not configured";


        $response = Http::post(self::Url(), [
            'receptor' => $endpoint->value,
            'message' => $endpoint->generateOTPMessage(),
        ]);
        try {
            if ($response instanceof Response) {
                return $response->json();
            } else {
                return $response->getMessage();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
