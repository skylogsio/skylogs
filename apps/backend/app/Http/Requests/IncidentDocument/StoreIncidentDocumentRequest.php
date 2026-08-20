<?php

namespace App\Http\Requests\IncidentDocument;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentDocumentType;
use App\Models\PostMortem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentDocumentRequest extends FormRequest
{
    /**
     * Content types accepted as incident evidence: screenshots, exported logs and charts,
     * and the report formats a review is usually written in.
     *
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'application/pdf',
        'text/plain',
        'text/csv',
        'text/markdown',
        'text/yaml',
        'application/x-yaml',
        'application/json',
        'application/zip',
        'application/gzip',
    ];

    private const MAX_KILOBYTES = 20480;

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
            'file' => [
                'required_without:externalUrl',
                'prohibits:externalUrl',
                'file',
                'max:'.self::MAX_KILOBYTES,
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
            'externalUrl' => ['required_without:file', 'nullable', 'url', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', Rule::enum(IncidentDocumentType::class)],
            'attachableType' => ['nullable', Rule::enum(IncidentDocumentAttachableType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Upload a file or provide an externalUrl.',
            'externalUrl.required_without' => 'Upload a file or provide an externalUrl.',
            'file.mimetypes' => 'The file type is not accepted. Allowed types: '.implode(', ', self::ALLOWED_MIME_TYPES).'.',
        ];
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
