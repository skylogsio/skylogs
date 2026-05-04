<?php

namespace App\Services\Config\SMS;

use App\Interfaces\Messageable;
use App\Models\Config\ConfigSms;
use App\Models\EndpointOTP;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SMSKaveNegarService
{
    private function Url(ConfigSms $config)
    {

        return 'https://api.kavenegar.com/v1/'.$config->apiToken.'/sms/send.json';
    }

    public function sendAlert(ConfigSms $config, $nums, Messageable $alert)
    {

        if (empty($nums)) {
            return '';
        }

        $result = Http::pool(function (Pool $pool) use ($config, $nums, $alert) {

            if ($nums instanceof Collection) {
                $numsString = $nums->implode(',');
            } else {
                $numsString = implode(',', $nums);
            }

            return $pool->get(self::Url($config), [
                'sender' => $config->senderNumber,
                'message' => $alert->smsMessage(),
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

    public function sendOTP(ConfigSms $config, EndpointOTP $endpoint)
    {

        $response = Http::get(self::Url($config), [
            'sender' => $config->senderNumber,
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
