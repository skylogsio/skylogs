<?php

namespace App\Services\Config\Call;

use App\Interfaces\Messageable;
use App\Models\Config\ConfigCall;
use App\Models\EndpointOTP;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CallKaveNegarService
{
    private function Url(ConfigCall $config)
    {
        return 'https://api.kavenegar.com/v1/'.$config->apiToken.'/call/maketts.json';
    }

    public function sendAlert(ConfigCall $config, $nums, Messageable $alert)
    {

        if (empty($nums)) {
            return '';
        }

        $result = Http::pool(function (Pool $pool) use ($nums, $alert, $config) {
            if ($nums instanceof Collection) {
                $numsString = $nums->implode(',');
            } else {
                $numsString = implode(',', $nums);
            }

            return $pool->get(self::Url($config), [
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

    public function sendOTP(ConfigCall $config, EndpointOTP $endpoint)
    {
        $response = Http::get(self::Url($config), [
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
