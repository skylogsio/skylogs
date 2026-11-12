<?php

namespace App\Http\Requests\IncidentActionItem;

use App\Enums\IncidentActionItemCategory;
use App\Enums\IncidentActionItemPriority;
use App\Enums\IncidentActionItemStatus;
use App\Http\Requests\Concerns\ValidatesMongoReferences;
use App\Models\PostMortem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for writing an action item.
 *
 * `postMortemId` is optional and, when given, must be the postmortem of the incident in
 * the route: an action item never crosses from one incident's review to another's.
 */
abstract class IncidentActionItemRequest extends FormRequest
{
    use ValidatesMongoReferences;

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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'ownerId' => ['nullable', 'string', 'size:24'],
            'teamId' => ['nullable', 'string', 'size:24'],
            'postMortemId' => ['nullable', 'string', 'size:24'],
            'priority' => ['nullable', Rule::enum(IncidentActionItemPriority::class)],
            'category' => ['nullable', Rule::enum(IncidentActionItemCategory::class)],
            'status' => ['nullable', Rule::enum(IncidentActionItemStatus::class)],
            'dueAt' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->assertReferencesExist($validator, 'ownerId', User::class, 'User', $this->input('ownerId'));
                $this->assertReferencesExist($validator, 'teamId', Team::class, 'Team', $this->input('teamId'));
                $this->assertPostMortemBelongsToIncident($validator);
            },
        ];
    }

    private function assertPostMortemBelongsToIncident(Validator $validator): void
    {
        $postMortemId = $this->input('postMortemId');

        if (! is_string($postMortemId) || $postMortemId === '') {
            return;
        }

        $belongs = PostMortem::query()
            ->where('_id', $postMortemId)
            ->where('incidentId', (string) $this->route('incidentId'))
            ->exists();

        if (! $belongs) {
            $validator->errors()->add(
                'postMortemId',
                "Postmortem '{$postMortemId}' does not belong to this incident.",
            );
        }
    }
}
