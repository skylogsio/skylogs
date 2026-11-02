<?php

namespace App\Http\Requests\Endpoint;

use App\Services\Notification\ChannelRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEndpointOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channels = app(ChannelRegistry::class);
        $type = (string) $this->input('type');

        return [
            'type' => ['required', 'string', Rule::in($channels->verifiableTypeValues())],
            'value' => ['required'],
            ...(in_array($type, $channels->verifiableTypeValues(), true) ? $channels->for($type)->rules() : []),
        ];
    }
}
