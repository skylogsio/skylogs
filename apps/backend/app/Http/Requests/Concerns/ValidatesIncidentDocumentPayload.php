<?php

namespace App\Http\Requests\Concerns;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentDocumentType;
use Illuminate\Validation\Rule;

trait ValidatesIncidentDocumentPayload
{
    /**
     * Content types accepted as incident evidence: screenshots, exported logs and charts,
     * and the report formats a review is usually written in.
     *
     * @var list<string>
     */
    private const ALLOWED_DOCUMENT_MIME_TYPES = [
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

    private const MAX_DOCUMENT_KILOBYTES = 20480;

    /**
     * @return array<string, mixed>
     */
    protected function incidentDocumentFieldRules(string $prefix = ''): array
    {
        $file = $this->prefixed($prefix, 'file');
        $externalUrl = $this->prefixed($prefix, 'externalUrl');

        return [
            $file => [
                'required_without:'.$externalUrl,
                'prohibits:'.$externalUrl,
                'file',
                'max:'.self::MAX_DOCUMENT_KILOBYTES,
                'mimetypes:'.implode(',', self::ALLOWED_DOCUMENT_MIME_TYPES),
            ],
            $externalUrl => ['required_without:'.$file, 'nullable', 'url', 'max:2048'],
            $this->prefixed($prefix, 'name') => ['nullable', 'string', 'max:255'],
            $this->prefixed($prefix, 'description') => ['nullable', 'string', 'max:2000'],
            $this->prefixed($prefix, 'type') => ['nullable', Rule::enum(IncidentDocumentType::class)],
            $this->prefixed($prefix, 'attachableType') => ['nullable', Rule::enum(IncidentDocumentAttachableType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function incidentDocumentFieldMessages(string $prefix = ''): array
    {
        $file = $this->prefixed($prefix, 'file');
        $externalUrl = $this->prefixed($prefix, 'externalUrl');
        $allowed = implode(', ', self::ALLOWED_DOCUMENT_MIME_TYPES);

        return [
            $file.'.required_without' => 'Upload a file or provide an externalUrl.',
            $externalUrl.'.required_without' => 'Upload a file or provide an externalUrl.',
            $file.'.mimetypes' => 'The file type is not accepted. Allowed types: '.$allowed.'.',
        ];
    }

    protected function prefixed(string $prefix, string $field): string
    {
        return $prefix === '' ? $field : $prefix.'.'.$field;
    }
}
