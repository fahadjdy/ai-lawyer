<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\StoreCaseNoteRequest;
use App\Models\CaseNote;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;

class CaseNoteController extends Controller
{
    public function store(StoreCaseNoteRequest $request, LegalCase $case): RedirectResponse
    {
        $case->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->value(),
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function update(StoreCaseNoteRequest $request, LegalCase $case, CaseNote $note): RedirectResponse
    {
        abort_unless($note->case_id === $case->id, 404);

        $note->update([
            'body' => $request->string('body')->value(),
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(LegalCase $case, CaseNote $note): RedirectResponse
    {
        $this->authorize('update', $case);
        abort_unless($note->case_id === $case->id, 404);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}
