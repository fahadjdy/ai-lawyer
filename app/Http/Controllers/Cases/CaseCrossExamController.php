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
 * Anticipates the cross-examination for a case: the questions opposing counsel
 * and the judge are each likely to ask, paired with a preparation strategy.
 * Backs the "Cross-examination prep" panel on the case detail page. Case-aware
 * (it considers the saved facts and tracking history) and the result is cached
 * so revisiting the case never regenerates needlessly.
 */
class CaseCrossExamController extends Controller
{
    public function __invoke(Request $request, LegalCase $case, CaseAiAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()->can('view', $case), 403);

        if (mb_strlen(trim((string) $case->description)) < 20) {
            return response()->json(['message' => 'Add a case description first so the assistant has something to work with.'], 422);
        }

        try {
            $result = $assistant->crossExamQuestions([
                'title' => $case->title,
                'description' => $case->description,
                'case_type' => $case->case_type?->value,
                'opposing_party' => $case->opposing_party,
                'court_name' => $case->court_name,
                'history' => $case->trackingHistory(),
            ]);

            CaseAiInsight::store($case, CaseAiInsight::KIND_CROSS_EXAM, $result, $request->user());

            return response()->json(['result' => $result, 'stale' => false]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
