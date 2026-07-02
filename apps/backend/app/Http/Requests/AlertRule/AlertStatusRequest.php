<?php

namespace App\Http\Requests\AlertRule;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AlertStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Per-alert-rule access is enforced in AlertRuleService::getAlertsStatusHistory().
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alertRuleIds' => ['required', 'array', 'min:1'],
            'alertRuleIds.*' => ['string', 'regex:/^[0-9a-fA-F]{24}$/'],
            'fromTime' => ['required', 'integer'],
            'toTime' => ['required', 'integer', 'gt:fromTime'],
            'bucketCount' => ['sometimes', 'integer', 'min:10', 'max:500'],
        ];
    }

    public function bucketCount(): int
    {
        return (int) ($this->validated('bucketCount') ?? 100);
    }
}
