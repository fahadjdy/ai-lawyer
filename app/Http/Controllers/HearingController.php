<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HearingStatus;
use App\Http\Requests\Hearings\StoreHearingRequest;
use App\Http\Requests\Hearings\UpdateHearingRequest;
use App\Http\Resources\HearingResource;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Notifications\HearingScheduledNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class HearingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Hearing::class);

        // Default to the current month window for the calendar view.
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth()->addMonth();

        $hearings = Hearing::with('case:id,uuid,case_number,title,status')
            ->between($from->toDateTimeString(), $to->toDateTimeString())
            ->orderBy('scheduled_at')
            ->get();

        return Inertia::render('hearings/Index', [
            'hearings' => HearingResource::collection($hearings),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'upcoming' => HearingResource::collection(
                Hearing::with('case:id,uuid,case_number,title')->upcoming()->limit(8)->get(),
            ),
            'options' => $this->formOptions(),
            'can' => ['manage' => $request->user()->can('create', Hearing::class)],
        ]);
    }

    public function store(StoreHearingRequest $request): RedirectResponse
    {
        $hearing = Hearing::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $this->notifyCaseTeam($hearing);

        return back()->with('success', 'Hearing scheduled.');
    }

    /**
     * Notify the case's lead lawyer & assignees (except the scheduler) that a
     * hearing was added. Failures are logged & swallowed.
     */
    private function notifyCaseTeam(Hearing $hearing): void
    {
        $case = LegalCase::with(['leadLawyer', 'assignees'])->find($hearing->case_id);

        if (! $case) {
            return;
        }

        $recipients = $case->assignees
            ->when($case->leadLawyer, fn ($c) => $c->push($case->leadLawyer))
            ->filter()
            ->unique('id')
            ->reject(fn ($u) => $u->id === auth()->id());

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new HearingScheduledNotification($hearing, $case));
        } catch (\Throwable $e) {
            Log::warning('Hearing notification failed: '.$e->getMessage(), ['hearing_id' => $hearing->id]);
        }
    }

    public function update(UpdateHearingRequest $request, Hearing $hearing): RedirectResponse
    {
        $hearing->update($request->validated());

        return back()->with('success', 'Hearing updated.');
    }

    public function destroy(Hearing $hearing): RedirectResponse
    {
        $this->authorize('delete', $hearing);

        $hearing->delete();

        return back()->with('success', 'Hearing deleted.');
    }

    /**
     * Enum + case options for the schedule/edit form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => HearingStatus::options(),
            'cases' => LegalCase::query()
                ->orderBy('title')
                ->get(['id', 'uuid', 'case_number', 'title'])
                ->map(fn (LegalCase $c) => ['id' => $c->id, 'name' => $c->case_number ? "{$c->case_number} — {$c->title}" : $c->title]),
        ];
    }
}
