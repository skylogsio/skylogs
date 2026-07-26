<?php

namespace App\Http\Requests\Ha;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyHaStateRequest extends FormRequest
{
    /**
     * Authorisation is the HaNodeAuth middleware's shared secret; there is no
     * user behind this request.
     */
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
            'key' => ['required', 'string', 'regex:/^alert:[0-9a-fA-F]{24}:[a-zA-Z]+:.+$/'],

            /*
             | Present but null is the tombstone that removes the slot, so the
             | field has to be sent even when it carries nothing.
             */
            'value' => ['present', 'nullable', 'array'],
            'value.version' => ['required_with:value', 'integer', 'min:1'],
            'value.nodeId' => ['required_with:value', 'string'],
            'value.state' => ['required_with:value', 'string'],
        ];
    }

    public function stateKey(): string
    {
        return (string) $this->validated('key');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stateValue(): ?array
    {
        /*
         | The raw input rather than the validated subset: the payload carries
         | per type fields that no rule names, and the applier needs all of it.
         */
        $value = $this->input('value');

        return is_array($value) ? $value : null;
    }
}
