<?php

namespace App\Http\Requests\IncidentActionItem;

use App\Enums\IncidentActionItemCategory;
use App\Enums\IncidentActionItemPriority;
use App\Enums\IncidentActionItemStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexIncidentActionItemRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(IncidentActionItemStatus::class)],
            'priority' => ['sometimes', Rule::enum(IncidentActionItemPriority::class)],
            'category' => ['sometimes', Rule::enum(IncidentActionItemCategory::class)],
            'ownerId' => ['sometimes', 'string'],
            'teamId' => ['sometimes', 'string'],
            'incidentId' => ['sometimes', 'string'],
            'open' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
