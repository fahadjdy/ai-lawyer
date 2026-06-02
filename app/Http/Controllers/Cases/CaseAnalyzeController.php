<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Models\CaseAiInsight;
use App\Models\LegalCase;
use App\Services\CaseAiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Analyzes a SAVED case — structured summary + suggested IPC sections — and
 * caches the result on the case. Backs the AI Case Assistant on the case detail
 * page (the draft create/edit flow uses {@see CaseAiController} instead, which
 * works on unsaved form fields and never persists). Case-aware: it reasons from
 * the stored facts and the tracking timeline.
 */
class CaseAnalyzeController extends Controller
{
    public function __invoke(Request $request, LegalCase $case, CaseAiAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()->can('view', $case), 403);

        if (mb_strlen(trim((string) $case->description)) < 20) {
            return response()->json(['message' => 'Add a case description first so the assistant has something to analyze.'], 422);
        }

        try {
            $result = $assistant->analyze([
                'title' => $case->title,
                'description' => $case->description,
                'case_type' => $case->case_type?->value,
                'opposing_party' => $case->opposing_party,
                'court_name' => $case->court_name,
                'history' => $case->trackingHistory(),
            ]);

            CaseAiInsight::store($case, CaseAiInsight::KIND_ANALYSIS, $result, $request->user());

            return response()->json(['result' => $result, 'stale' => false]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
