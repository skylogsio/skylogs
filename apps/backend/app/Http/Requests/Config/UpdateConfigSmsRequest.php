<?php

namespace App\Http\Requests\Config;

use App\Enums\EndpointType;
use App\Enums\SmsProviderType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigSmsRequest extends FormRequest
{
    use ValidatesProviderConfig;

    protected function endpointType(): EndpointType
    {
        return EndpointType::SMS;
    }

    protected function providerEnum(): string
    {
        return SmsProviderType::class;
    }
}
