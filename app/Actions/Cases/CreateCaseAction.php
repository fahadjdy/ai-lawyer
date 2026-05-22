<?php

declare(strict_types=1);

namespace App\Actions\Cases;

use App\DTOs\CaseData;
use App\Events\CaseCreated;
use App\Models\LegalCase;
use App\Repositories\CaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Creates a case, assigns its team of lawyers and emits the {@see CaseCreated}
 * event (which fans out notifications/audit on the queue). Wrapped in a
 * transaction so the case + pivot assignments commit atomically.
 */
class CreateCaseAction
{
    public function __construct(private readonly CaseRepository $repository) {}

    public function execute(CaseData $data): LegalCase
    {
        return DB::transaction(function () use ($data): LegalCase {
            $attributes = $data->toArray();

            $attributes['case_number'] ??= $this->repository->nextCaseNumber();
            $attributes['created_by'] = auth()->id();

            /** @var LegalCase $case */
            $case = $this->repository->create($attributes);

            if (($assignees = $data->assigneeIds()) !== null) {
                $case->assignees()->sync($assignees);
            }

            event(new CaseCreated($case));

            return $case->load(['client', 'leadLawyer']);
        });
    }
}
