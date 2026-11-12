<?php

namespace App\Services;

use App\Enums\IncidentDocumentAttachableType;
use App\Enums\IncidentDocumentType;
use App\Models\Incident;
use App\Models\IncidentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Evidence attached to an incident or its postmortem.
 *
 * Files go to the configured filesystem disk and are only ever handed out through a
 * short-lived signed URL, so the storage layout is never exposed to a client.
 */
class IncidentDocumentService
{
    private const DOWNLOAD_URL_MINUTES = 10;

    /**
     * @return Builder<IncidentDocument>
     */
    public function query(Incident $incident): Builder
    {
        return IncidentDocument::query()
            ->with('uploadedByUser')
            ->where('incidentId', (string) $incident->id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(
        User $user,
        Incident $incident,
        array $validated,
        ?UploadedFile $file,
        ?string $postMortemId,
    ): IncidentDocument {
        $attachableType = IncidentDocumentAttachableType::from(
            $validated['attachableType'] ?? IncidentDocumentAttachableType::Incident->value,
        );

        $attributes = [
            'incidentId' => (string) $incident->id,
            'attachableType' => $attachableType,
            'attachableId' => $attachableType === IncidentDocumentAttachableType::PostMortem
                ? $postMortemId
                : (string) $incident->id,
            'type' => IncidentDocumentType::from($validated['type'] ?? IncidentDocumentType::Other->value),
            'description' => (string) ($validated['description'] ?? ''),
            'uploadedBy' => $user->id,
        ];

        $document = $file === null
            ? IncidentDocument::create([
                ...$attributes,
                'name' => $validated['name'] ?? $validated['externalUrl'],
                'externalUrl' => $validated['externalUrl'],
                'disk' => null,
                'path' => null,
                'fileName' => null,
                'mimeType' => null,
                'size' => null,
            ])
            : IncidentDocument::create([
                ...$attributes,
                'name' => $validated['name'] ?? $file->getClientOriginalName(),
                'externalUrl' => null,
                'disk' => $this->disk(),
                'path' => $this->storeFile($incident, $file),
                'fileName' => $file->getClientOriginalName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

        $document->load('uploadedByUser');

        return $document;
    }

    public function delete(IncidentDocument $document): void
    {
        if ($document->path !== null) {
            Storage::disk($document->disk ?? $this->disk())->delete($document->path);
        }

        $document->delete();
    }

    public function deleteForIncident(Incident $incident): void
    {
        foreach ($this->query($incident)->get() as $document) {
            $this->delete($document);
        }
    }

    /**
     * External links are handed back unchanged; uploads get a signed, expiring route.
     *
     * @return array{url: string, expiresAt: string|null}
     */
    public function downloadUrl(IncidentDocument $document): array
    {
        if ($document->externalUrl !== null) {
            return ['url' => $document->externalUrl, 'expiresAt' => null];
        }

        $expiresAt = now()->addMinutes(self::DOWNLOAD_URL_MINUTES);

        return [
            'url' => URL::temporarySignedRoute(
                'incident.document.download',
                $expiresAt,
                ['documentId' => (string) $document->id],
            ),
            'expiresAt' => $expiresAt->toISOString(),
        ];
    }

    private function storeFile(Incident $incident, UploadedFile $file): string
    {
        return $file->storeAs(
            'incidents/'.$incident->id.'/documents',
            Str::uuid()->toString().'.'.($file->getClientOriginalExtension() ?: 'bin'),
            $this->disk(),
        );
    }

    private function disk(): string
    {
        return (string) config('filesystems.default');
    }
}
