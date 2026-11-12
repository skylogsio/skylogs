<?php

namespace App\Http\Requests\Concerns;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentSource;
use App\Models\Incident;
use App\Models\PostMortem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\UploadedFile;

trait ValidatesIncidentNestedDocumentation
{
    use ValidatesIncidentDocumentPayload;
    use ValidatesPostMortemPayload {
        ValidatesIncidentDocumentPayload::prefixed insteadof ValidatesPostMortemPayload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function nestedDocumentationRules(): array
    {
        return [
            'postMortem' => ['nullable', 'array'],
            ...$this->postMortemFieldRules('postMortem'),
            'documents' => ['nullable', 'array'],
            'documents.*' => ['required', 'array'],
            ...$this->incidentDocumentFieldRules('documents.*'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function nestedDocumentationMessages(): array
    {
        return $this->incidentDocumentFieldMessages('documents.*');
    }

    /**
     * @return list<callable>
     */
    protected function nestedDocumentationAfter(): array
    {
        return [
            function (Validator $validator) {
                if (is_array($this->input('postMortem'))) {
                    $this->assertPostMortemReferences($validator, 'postMortem');
                }

                $this->assertNestedPostMortemDocuments($validator);
                $this->assertManualSourceForNestedDocumentation($validator);
            },
        ];
    }

    /**
     * @return array<int, UploadedFile|null>
     */
    public function documentFiles(): array
    {
        $documents = $this->input('documents', []);

        if (! is_array($documents)) {
            return [];
        }

        $files = [];

        foreach (array_keys($documents) as $index) {
            $file = $this->file('documents.'.$index.'.file');
            $files[(int) $index] = $file instanceof UploadedFile ? $file : null;
        }

        return $files;
    }

    private function assertNestedPostMortemDocuments(Validator $validator): void
    {
        $documents = $this->input('documents', []);

        if (! is_array($documents)) {
            return;
        }

        $hasPostMortemPayload = is_array($this->input('postMortem'));
        $incidentHasPostMortem = $this->existingPostMortemId() !== null;

        foreach ($documents as $index => $document) {
            if (! is_array($document)) {
                continue;
            }

            if (($document['attachableType'] ?? null) !== IncidentDocumentAttachableType::PostMortem->value) {
                continue;
            }

            if ($hasPostMortemPayload || $incidentHasPostMortem) {
                continue;
            }

            $validator->errors()->add(
                'documents.'.$index.'.attachableType',
                'This incident has no postmortem yet, so a document cannot be attached to one.',
            );
        }
    }

    private function assertManualSourceForNestedDocumentation(Validator $validator): void
    {
        $incidentId = $this->route('id');

        if (! is_string($incidentId)) {
            return;
        }

        $hasNested = is_array($this->input('postMortem'))
            || (is_array($this->input('documents')) && $this->input('documents') !== []);

        if (! $hasNested) {
            return;
        }

        $incident = Incident::query()->where('_id', $incidentId)->first();

        if ($incident === null || $incident->source !== IncidentSource::Policy) {
            return;
        }

        $message = 'Documents and postmortem can only be attached on create or update for manual incidents.';

        if (is_array($this->input('postMortem'))) {
            $validator->errors()->add('postMortem', $message);
        }

        if (is_array($this->input('documents')) && $this->input('documents') !== []) {
            $validator->errors()->add('documents', $message);
        }
    }

    private function existingPostMortemId(): ?string
    {
        $incidentId = $this->route('id');

        if (! is_string($incidentId)) {
            return null;
        }

        $postMortem = PostMortem::query()
            ->where('incidentId', $incidentId)
            ->first();

        return $postMortem === null ? null : (string) $postMortem->id;
    }
}
