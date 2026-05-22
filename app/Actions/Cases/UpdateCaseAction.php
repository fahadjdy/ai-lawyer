<?php

declare(strict_types=1);

namespace App\Actions\Cases;

use App\DTOs\CaseData;
use App\Models\LegalCase;
use App\Repositories\CaseRepository;
use Illuminate\Support\Facades\DB;

class UpdateCaseAction
{
    public function __construct(private readonly CaseRepository $repository) {}

    public function execute(LegalCase $case, CaseData $data): LegalCase
    {
        return DB::transaction(function () use ($case, $data): LegalCase {
            $this->repository->update($case, $data->toArray());

            if (($assignees = $data->assigneeIds()) !== null) {
                $case->assignees()->sync($assignees);
            }

            return $case->load(['client', 'leadLawyer', 'assignees']);
        });
    }
}
