<?php

namespace App\Http\Requests\Endpoint;

use App\Enums\EndpointType;
use App\Services\Notification\ChannelRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Shared validation for writing an endpoint. The allowed types and each
 * type's own fields come from the notification channel registry, so a new
 * channel is accepted here without touching this class.
 */
abstract class EndpointRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in([...$channels->typeValues(), EndpointType::FLOW->value])],
            'onCall' => ['nullable', 'boolean'],
            'isPublic' => ['nullable', 'boolean'],
            'accessUserIds' => ['nullable', 'array'],
            'accessUserIds.*' => ['string'],
            'accessTeamIds' => ['nullable', 'array'],
            'accessTeamIds.*' => ['string'],
            'otpCode' => ['nullable'],
            'steps' => ['nullable', 'array'],
            ...($channels->has($type) ? $channels->for($type)->rules() : []),
        ];
    }

    /**
     * The frontend reads `status: false` from a 200 response for invalid
     * endpoint input, so that contract is kept instead of the default 422.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'errors' => $validator->errors(),
        ]));
    }
}
