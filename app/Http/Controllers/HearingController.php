<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HearingStatus;
use App\Http\Requests\Hearings\StoreHearingRequest;
use App\Http\Requests\Hearings\UpdateHearingRequest;
use App\Http\Resources\HearingResource;
use App\Models\Hearing;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        Hearing::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Hearing scheduled.');
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
