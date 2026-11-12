<?php

namespace App\Http\Requests\IncidentPolicy;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportIncidentPolicyRequest extends FormRequest
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
            'file' => ['required_without:yaml', 'file', 'extensions:yaml,yml', 'max:512'],
            'yaml' => ['required_without:file', 'string', 'max:262144'],
            'dryRun' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Upload a YAML file or send the definition in the yaml field.',
            'yaml.required_without' => 'Upload a YAML file or send the definition in the yaml field.',
        ];
    }

    /**
     * The definition, whether it arrived as an upload or as a raw string.
     */
    public function definition(): string
    {
        $file = $this->file('file');

        if ($file !== null) {
            return (string) file_get_contents($file->getRealPath());
        }

        return (string) $this->validated('yaml');
    }

    public function isDryRun(): bool
    {
        return $this->boolean('dryRun');
    }
}
