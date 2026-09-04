<?php

namespace App\Http\Requests\IncidentDocument;

use App\Enums\IncidentDocumentAttachableType;
use App\Http\Requests\Concerns\ValidatesIncidentDocumentPayload;
use App\Models\PostMortem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncidentDocumentRequest extends FormRequest
{
    use ValidatesIncidentDocumentPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->incidentDocumentFieldRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->incidentDocumentFieldMessages();
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            $this->assertPostMortemExists(...),
        ];
    }

    /**
     * A document can only hang off a postmortem that has actually been started.
     */
    private function assertPostMortemExists(Validator $validator): void
    {
        if ($this->input('attachableType') !== IncidentDocumentAttachableType::PostMortem->value) {
            return;
        }

        if ($this->postMortemId() === null) {
            $validator->errors()->add(
                'attachableType',
                'This incident has no postmortem yet, so a document cannot be attached to one.',
            );
        }
    }

    public function postMortemId(): ?string
    {
        $postMortem = PostMortem::query()
            ->where('incidentId', (string) $this->route('incidentId'))
            ->first();

        return $postMortem === null ? null : (string) $postMortem->id;
    }
}
