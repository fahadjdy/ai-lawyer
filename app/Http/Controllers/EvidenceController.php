<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvidenceController extends Controller
{
    public function index(Request $request): Response
    {
        $evidence = Evidence::with('case:id,uuid,case_number,title')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Evidence $e) => [
                'id' => $e->uuid,
                'reference_number' => $e->reference_number,
                'title' => $e->title,
                'type' => ['value' => $e->type->value, 'label' => $e->type->label()],
                'status' => ['value' => $e->status->value, 'label' => $e->status->label(), 'color' => $e->status->color()],
                'collected_at' => $e->collected_at?->toIso8601String(),
                'case' => $e->case ? ['id' => $e->case->uuid, 'case_number' => $e->case->case_number] : null,
            ]);

        return Inertia::render('evidence/Index', [
            'evidence' => $evidence,
            'filters' => $request->only(['status', 'type']),
            'options' => ['statuses' => EvidenceStatus::options(), 'types' => EvidenceType::options()],
        ]);
    }
}
