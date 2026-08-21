<?php

namespace App\Http\Controllers\V1\Incident;

use App\Http\Requests\IncidentDocument\IndexIncidentDocumentRequest;
use App\Http\Requests\IncidentDocument\StoreIncidentDocumentRequest;
use App\Http\Resources\IncidentDocument\IncidentDocumentResource;
use App\Http\Resources\PaginatedJson;
use App\Models\IncidentDocument;
use App\Services\IncidentDocumentService;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends IncidentSubResourceController
{
    public function __construct(
        IncidentService $incidentService,
        private readonly IncidentDocumentService $documentService,
    ) {
        parent::__construct($incidentService);
    }

    public function index(IndexIncidentDocumentRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);
        $perPage = (int) ($request->validated('perPage') ?? 25);
        $canEdit = $this->incidentService->canEdit(auth()->user(), $incident);

        $query = $this->documentService->query($incident);

        if ($request->filled('type')) {
            $query->where('type', $request->validated('type'));
        }

        if ($request->filled('attachableType')) {
            $query->where('attachableType', $request->validated('attachableType'));
        }

        $paginator = $query->orderByDesc('createdAt')->paginate($perPage);

        foreach ($paginator as $document) {
            $document->setAttribute('canDelete', $canEdit);
        }

        return PaginatedJson::make($paginator, IncidentDocumentResource::class);
    }

    public function store(StoreIncidentDocumentRequest $request, string $incidentId): JsonResponse
    {
        $incident = $this->editableIncident($incidentId);

        $document = $this->documentService->create(
            auth()->user(),
            $incident,
            $request->validated(),
            $request->file('file'),
            $request->postMortemId(),
        );

        $document->setAttribute('canDelete', true);

        return (new IncidentDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(string $incidentId, string $documentId): JsonResponse
    {
        $incident = $this->editableIncident($incidentId);

        $this->documentService->delete($this->findDocument($incident->id, $documentId));

        return response()->json(['status' => true]);
    }

    /**
     * Hands out a short-lived signed link rather than the file, so the browser can fetch
     * it directly without carrying the bearer token into an <img> or download attribute.
     */
    public function downloadUrl(string $incidentId, string $documentId): JsonResponse
    {
        $incident = $this->viewableIncident($incidentId);

        return response()->json(
            $this->documentService->downloadUrl($this->findDocument($incident->id, $documentId)),
        );
    }

    /**
     * Reached through a signed URL, which is what authorises the request here.
     */
    public function download(string $documentId): StreamedResponse
    {
        $document = IncidentDocument::query()->where('_id', $documentId)->firstOrFail();

        if ($document->path === null) {
            abort(404);
        }

        return Storage::disk($document->disk)->download($document->path, $document->fileName);
    }

    private function findDocument(string $incidentId, string $documentId): IncidentDocument
    {
        return IncidentDocument::query()
            ->where('_id', $documentId)
            ->where('incidentId', $incidentId)
            ->firstOrFail();
    }
}
