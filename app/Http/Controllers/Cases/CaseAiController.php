<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Services\CaseAiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Analyzes a draft case (as typed in the form, not necessarily saved) and
 * returns an AI-structured summary + suggested IPC sections. Backs the AI Case
 * Assistant panel on the case create/edit screens.
 */
class CaseAiController extends Controller
{
    public function __invoke(Request $request, CaseAiAssistant $assistant): JsonResponse
    {
        // Available while authoring a case (creating or editing).
        abort_unless(
            $request->user()->can('cases.create') || $request->user()->can('cases.update'),
            403,
        );

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'case_type' => ['nullable', 'string', 'max:60'],
            'opposing_party' => ['nullable', 'string', 'max:255'],
            'court_name' => ['nullable', 'string', 'max:255'],
            // Optional case tracking timeline (oldest-first) for context-aware re-analysis.
            'history' => ['nullable', 'array', 'max:60'],
            'history.*.stage' => ['nullable', 'string', 'max:60'],
            'history.*.title' => ['nullable', 'string', 'max:255'],
            'history.*.sections' => ['nullable', 'array'],
            'history.*.sections.*' => ['string', 'max:60'],
            'history.*.notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'description.required' => 'Add a case description first so the assistant has something to analyze.',
            'description.min' => 'Please add a little more detail (at least 20 characters) before analyzing.',
        ]);

        try {
            return response()->json(['result' => $assistant->analyze($data)]);
        } catch (Throwable $e) {
            // Surface a clean message to the panel (e.g. missing key, upstream error).
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
