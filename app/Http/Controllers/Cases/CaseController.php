<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cases;

use App\DTOs\CaseData;
use App\Enums\CasePriority;
use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\StoreCaseRequest;
use App\Http\Requests\Cases\UpdateCaseRequest;
use App\Http\Resources\CaseListResource;
use App\Http\Resources\CaseResource;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Thin Inertia controller for the Cases module. All business logic lives in
 * {@see CaseService}; the controller only authorizes, validates (via Form
 * Requests) and shapes the Inertia response.
 */
class CaseController extends Controller
{
    public function __construct(private readonly CaseService $cases) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LegalCase::class);

        $filters = $request->only(['search', 'status', 'priority', 'case_type', 'sort', 'only_active']);

        $cases = $this->cases->paginate($filters, $request->integer('per_page', 15));

        return Inertia::render('cases/Index', [
            'cases' => CaseListResource::collection($cases),
            'filters' => $filters,
            'options' => $this->filterOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', LegalCase::class);

        return Inertia::render('cases/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreCaseRequest $request): RedirectResponse
    {
        $case = $this->cases->create(CaseData::fromRequest($request));

        return redirect()
            ->route('cases.show', $case->uuid)
            ->with('success', "Case {$case->case_number} created.");
    }

    public function show(LegalCase $case): Response
    {
        $this->authorize('view', $case);

        return Inertia::render('cases/Show', [
            'case' => new CaseResource($this->cases->find($case->uuid)),
            'stages' => CaseStage::options(),
        ]);
    }

    public function edit(LegalCase $case): Response
    {
        $this->authorize('update', $case);

        // Flat, form-friendly payload (integer FKs the <select>s bind to).
        return Inertia::render('cases/Edit', [
            'caseUuid' => $case->uuid,
            'case' => [
                'title' => $case->title,
                'case_number' => $case->case_number,
                'client_id' => $case->client_id,
                'case_type' => $case->case_type->value,
                'status' => $case->status->value,
                'priority' => $case->priority->value,
                'description' => $case->description,
                'court_name' => $case->court_name,
                'court_type' => $case->court_type,
                'jurisdiction' => $case->jurisdiction,
                'judge_name' => $case->judge_name,
                'opposing_party' => $case->opposing_party,
                'opposing_counsel' => $case->opposing_counsel,
                'filing_date' => $case->filing_date?->toDateString(),
                'next_hearing_at' => $case->next_hearing_at?->format('Y-m-d\TH:i'),
                'lead_lawyer_id' => $case->lead_lawyer_id,
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateCaseRequest $request, LegalCase $case): RedirectResponse
    {
        $this->cases->update($case, CaseData::fromRequest($request));

        return redirect()
            ->route('cases.show', $case->uuid)
            ->with('success', 'Case updated.');
    }

    public function destroy(LegalCase $case): RedirectResponse
    {
        $this->authorize('delete', $case);

        $this->cases->delete($case);

        return redirect()
            ->route('cases.index')
            ->with('success', 'Case archived.');
    }

    /**
     * Enum-driven options for the index filter bar.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => CaseStatus::options(),
            'priorities' => CasePriority::options(),
            'types' => CaseType::options(),
        ];
    }

    /**
     * Options for the create/edit form: enums + the firm's clients & lawyers.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            ...$this->filterOptions(),
            'clients' => Client::query()
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'company'])
                ->map(fn (Client $c) => ['id' => $c->id, 'name' => $c->company ? "{$c->name} ({$c->company})" : $c->name]),
            'lawyers' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'designation'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'designation' => $u->designation]),
        ];
    }
}
