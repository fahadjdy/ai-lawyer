<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\StoreCaseEventRequest;
use App\Http\Requests\Cases\UpdateCaseEventRequest;
use App\Models\CaseEvent;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;

/**
 * Manages the Case Tracking timeline — stage-by-stage updates that record how a
 * case progresses and how its applicable sections change after investigation.
 */
class CaseEventController extends Controller
{
    public function store(StoreCaseEventRequest $request, LegalCase $case): RedirectResponse
    {
        $data = $request->validated();
        $newStatus = $data['case_status'] ?? null;
        unset($data['case_status']);

        $case->events()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        // Optionally advance the case's overall status with this tracking update.
        if ($newStatus !== null && $newStatus !== $case->status->value) {
            $case->update(['status' => $newStatus]);
        }

        return back()->with('success', 'Case update added.');
    }

    public function update(UpdateCaseEventRequest $request, LegalCase $case, CaseEvent $event): RedirectResponse
    {
        $this->ensureBelongs($case, $event);

        $event->update($request->validated());

        return back()->with('success', 'Case update saved.');
    }

    public function destroy(LegalCase $case, CaseEvent $event): RedirectResponse
    {
        $this->authorize('update', $case);
        $this->ensureBelongs($case, $event);

        $event->delete();

        return back()->with('success', 'Case update removed.');
    }

    private function ensureBelongs(LegalCase $case, CaseEvent $event): void
    {
        abort_unless($event->case_id === $case->id, 404);
    }
}
