<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Models\LegalCase;
use App\Services\CaseAiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Suggests applicable IPC sections for a single case-tracking update, using the
 * typed title/notes as the focus and the case itself as context. Backs the
 * "auto-fill sections from title" action on the tracking form.
 */
class SuggestSectionsController extends Controller
{
    public function __invoke(Request $request, LegalCase $case, CaseAiAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()->can('update', $case), 403);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $sections = $assistant->suggestSections([
                'title' => $case->title,
                'description' => $case->description,
                'case_type' => $case->case_type?->value,
                'focus' => $data['text'] ?? '',
            ]);

            return response()->json(['sections' => $sections]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
