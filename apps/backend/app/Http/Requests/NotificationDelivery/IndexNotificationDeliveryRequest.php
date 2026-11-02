<?php

namespace App\Http\Requests\NotificationDelivery;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNotificationDeliveryRequest extends FormRequest
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
        return [
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'notifyId' => ['sometimes', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'endpointId' => ['sometimes', 'string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'status' => ['sometimes', Rule::enum(NotificationDeliveryStatus::class)],
        ];
    }
}
