<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Http\Requests\Evidence\StoreCustodyEntryRequest;
use App\Http\Requests\Evidence\StoreEvidenceRequest;
use App\Http\Requests\Evidence\UpdateEvidenceRequest;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\LegalCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvidenceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Evidence::class);

        $evidence = Evidence::with(['case:id,uuid,case_number,title', 'document:id,uuid,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Evidence $e) => $this->row($e));

        return Inertia::render('evidence/Index', [
            'evidence' => $evidence,
            'filters' => $request->only(['status', 'type']),
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreEvidenceRequest $request): RedirectResponse
    {
        Evidence::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Evidence recorded.');
    }

    public function show(Evidence $evidence): Response
    {
        $this->authorize('view', $evidence);

        $evidence->load(['case:id,uuid,case_number,title', 'document:id,uuid,name,extension', 'creator:id,name']);

        return Inertia::render('evidence/Show', [
            'evidence' => [
                ...$this->row($evidence),
                'document' => $evidence->document
                    ? ['id' => $evidence->document->uuid, 'name' => $evidence->document->name, 'extension' => $evidence->document->extension]
                    : null,
                'created_by' => $evidence->creator?->name,
                'chain_of_custody' => $evidence->chain_of_custody ?? [],
                'created_at' => $evidence->created_at?->toIso8601String(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateEvidenceRequest $request, Evidence $evidence): RedirectResponse
    {
        $evidence->update($request->validated());

        return back()->with('success', 'Evidence updated.');
    }

    public function destroy(Evidence $evidence): RedirectResponse
    {
        $this->authorize('delete', $evidence);

        $evidence->delete();

        return redirect()->route('evidence.index')->with('success', 'Evidence deleted.');
    }

    /**
     * Append an immutable entry to the chain-of-custody audit trail.
     */
    public function addCustody(StoreCustodyEntryRequest $request, Evidence $evidence): RedirectResponse
    {
        $chain = $evidence->chain_of_custody ?? [];
        $chain[] = [
            'action' => $request->string('action')->value(),
            'handler' => $request->string('handler')->value(),
            'note' => $request->filled('note') ? $request->string('note')->value() : null,
            'occurred_at' => ($request->date('occurred_at') ?? now())->toIso8601String(),
            'logged_by' => $request->user()->name,
            'logged_at' => now()->toIso8601String(),
        ];

        $evidence->update(['chain_of_custody' => $chain]);

        return back()->with('success', 'Custody entry added.');
    }

    /**
     * Shared row shape used by both the list and the detail header.
     *
     * @return array<string, mixed>
     */
    private function row(Evidence $e): array
    {
        return [
            'id' => $e->uuid,
            'reference_number' => $e->reference_number,
            'title' => $e->title,
            'description' => $e->description,
            'type' => ['value' => $e->type->value, 'label' => $e->type->label()],
            'status' => ['value' => $e->status->value, 'label' => $e->status->label(), 'color' => $e->status->color()],
            'collected_at' => $e->collected_at?->toIso8601String(),
            'collected_by' => $e->collected_by,
            'case' => $e->case ? ['id' => $e->case->uuid, 'case_number' => $e->case->case_number, 'title' => $e->case->title] : null,
            'case_id' => $e->case_id,
            'document_id' => $e->document_id,
        ];
    }

    /**
     * Enum + relation options for the create/edit form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => EvidenceStatus::options(),
            'types' => EvidenceType::options(),
            'cases' => LegalCase::query()
                ->orderBy('title')
                ->get(['id', 'case_number', 'title'])
                ->map(fn (LegalCase $c) => ['id' => $c->id, 'name' => $c->case_number ? "{$c->case_number} — {$c->title}" : $c->title])
                ->all(),
            'documents' => Document::query()
                ->latestVersions()
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'name', 'extension'])
                ->map(fn (Document $d) => ['id' => $d->id, 'name' => $d->extension ? "{$d->name}.{$d->extension}" : $d->name])
                ->all(),
        ];
    }
}
