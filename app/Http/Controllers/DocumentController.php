<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = Document::with(['case:id,uuid,case_number,title', 'uploader:id,uuid,name'])
            ->latestVersions()
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $d) => [
                'id' => $d->uuid,
                'name' => $d->name,
                'extension' => $d->extension,
                'mime_type' => $d->mime_type,
                'size' => $d->humanSize(),
                'version' => $d->version,
                'case' => $d->case ? ['id' => $d->case->uuid, 'case_number' => $d->case->case_number] : null,
                'uploaded_by' => $d->uploader?->name,
                'created_at' => $d->created_at?->toIso8601String(),
            ]);

        return Inertia::render('documents/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search']),
        ]);
    }
}
