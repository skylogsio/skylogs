<?php

namespace App\Http\Requests\Ha;

use App\Services\Ha\RaftClient;
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
     * The sidecar notifies with the value exactly as it stored it, which is the
     * raw JSON text of the slot rather than an object. Decoding here keeps the
     * rules below, and everything downstream, working on one shape.
     *
     * A string that is not an object is left alone so validation rejects it: a
     * payload nobody can read must not be mistaken for a tombstone.
     */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        if (! is_string($value)) {
            return;
        }

        $decoded = RaftClient::decodeStoredValue($value);

        if ($decoded !== null) {
            $this->merge(['value' => $decoded]);
        }
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
