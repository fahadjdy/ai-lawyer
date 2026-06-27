<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Documents\StoreFolderRequest;
use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Http\RedirectResponse;

class DocumentFolderController extends Controller
{
    public function store(StoreFolderRequest $request): RedirectResponse
    {
        DocumentFolder::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Folder created.');
    }

    public function destroy(DocumentFolder $folder): RedirectResponse
    {
        $this->authorize('create', Document::class);
        abort_unless($folder->team_id === $this->teamId(), 403);

        // Detach contained documents so they remain accessible, then remove the folder.
        Document::where('folder_id', $folder->id)->update(['folder_id' => null]);
        $folder->delete();

        return back()->with('success', 'Folder deleted.');
    }

    private function teamId(): ?int
    {
        return auth()->user()?->team_id;
    }
}
