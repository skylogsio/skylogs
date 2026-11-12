<?php

namespace App\Http\Requests\Config;

use App\Enums\CallProviderType;
use App\Enums\EndpointType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConfigCallRequest extends FormRequest
{
    use ValidatesProviderConfig;

    protected function endpointType(): EndpointType
    {
        return EndpointType::CALL;
    }

    protected function providerEnum(): string
    {
        return CallProviderType::class;
    }
}
