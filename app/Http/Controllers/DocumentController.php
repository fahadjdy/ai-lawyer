<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentVersionRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        $documents = Document::with(['case:id,uuid,case_number,title', 'uploader:id,uuid,name', 'folder:id,uuid,name'])
            ->latestVersions()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('folder'), fn ($q) => $q->whereHas('folder', fn ($f) => $f->where('uuid', $request->string('folder'))))
            ->withCount('versions')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $d) => [
                'id' => $d->uuid,
                'name' => $d->name,
                'original_name' => $d->original_name,
                'extension' => $d->extension,
                'mime_type' => $d->mime_type,
                'size' => $d->humanSize(),
                'version' => $d->version,
                'versions_count' => $d->versions_count,
                'case' => $d->case ? ['id' => $d->case->uuid, 'case_number' => $d->case->case_number] : null,
                'case_id' => $d->case_id,
                'folder' => $d->folder ? ['id' => $d->folder->uuid, 'name' => $d->folder->name] : null,
                'folder_id' => $d->folder_id,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at?->toIso8601String(),
            ]);

        return Inertia::render('documents/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search', 'folder']),
            'folders' => $this->folderOptions(),
            'options' => ['cases' => $this->caseOptions()],
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $meta = $this->storeFile($file, $request->user()->team_id);

        Document::create([
            'case_id' => $request->integer('case_id') ?: null,
            'folder_id' => $request->integer('folder_id') ?: null,
            'version' => 1,
            'is_latest' => true,
            'name' => $request->filled('name')
                ? $request->string('name')->value()
                : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'uploaded_by' => $request->user()->id,
            ...$meta,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $document->update($request->validated());

        return back()->with('success', 'Document updated.');
    }

    /**
     * Upload a new version of an existing document. The chain is keyed by the
     * root (v1); all siblings are flipped to not-latest and the fresh upload
     * becomes the current version.
     */
    public function storeVersion(StoreDocumentVersionRequest $request, Document $document): RedirectResponse
    {
        $root = $document->parent_id ? $document->parent : $document;
        $ids = $root->versions()->pluck('id')->push($root->id);
        $nextVersion = (int) Document::whereIn('id', $ids)->max('version') + 1;

        Document::whereIn('id', $ids)->update(['is_latest' => false]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $meta = $this->storeFile($file, $request->user()->team_id);

        Document::create([
            'case_id' => $root->case_id,
            'client_id' => $root->client_id,
            'folder_id' => $root->folder_id,
            'parent_id' => $root->id,
            'version' => $nextVersion,
            'is_latest' => true,
            'name' => $root->name,
            'uploaded_by' => $request->user()->id,
            ...$meta,
        ]);

        return back()->with('success', "Version {$nextVersion} uploaded.");
    }

    public function download(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404, 'File no longer exists.');

        $filename = $document->name.($document->extension ? '.'.$document->extension : '');

        return $disk->download($document->path, $filename);
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $wasLatest = $document->is_latest;
        $root = $document->parent_id ? $document->parent : $document;
        $ids = $root->versions()->pluck('id')->push($root->id);

        $document->delete();

        // Promote the next-newest surviving version so a "latest" always exists.
        if ($wasLatest) {
            Document::whereIn('id', $ids)
                ->where('id', '!=', $document->id)
                ->orderByDesc('version')
                ->first()?->update(['is_latest' => true]);
        }

        return back()->with('success', 'Document deleted.');
    }

    /**
     * Persist the upload to the configured disk and return the column metadata.
     *
     * @return array<string, mixed>
     */
    private function storeFile(UploadedFile $file, ?int $teamId): array
    {
        $disk = config('filesystems.default', 'local');
        $extension = strtolower($file->getClientOriginalExtension());
        $stored = $teamId.'/'.Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $file->storeAs('documents', $stored, $disk);

        return [
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension ?: null,
            'size' => $file->getSize(),
            'hash' => hash_file('sha256', $file->getRealPath()) ?: null,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function caseOptions(): array
    {
        return LegalCase::query()
            ->orderBy('title')
            ->get(['id', 'case_number', 'title'])
            ->map(fn (LegalCase $c) => ['id' => $c->id, 'name' => $c->case_number ? "{$c->case_number} — {$c->title}" : $c->title])
            ->all();
    }

    /**
     * @return array<int, array{id: int, uuid: string, name: string}>
     */
    private function folderOptions(): array
    {
        return DocumentFolder::query()
            ->orderBy('name')
            ->get(['id', 'uuid', 'name'])
            ->map(fn (DocumentFolder $f) => ['id' => $f->id, 'uuid' => $f->uuid, 'name' => $f->name])
            ->all();
    }
}
