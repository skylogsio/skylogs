<?php

namespace App\Http\Requests\IncidentDocument;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIncidentDocumentRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(IncidentDocumentType::class)],
            'attachableType' => ['sometimes', Rule::enum(IncidentDocumentAttachableType::class)],
        ];
    }
}
