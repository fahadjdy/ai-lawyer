<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Cases\CreateCaseAction;
use App\Actions\Cases\UpdateCaseAction;
use App\DTOs\CaseData;
use App\Models\LegalCase;
use App\Repositories\CaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Application service for the Cases module. Controllers depend on this single
 * entry point; it delegates reads to the repository and writes to actions,
 * keeping controllers thin and the domain logic testable in isolation.
 */
class CaseService
{
    public function __construct(
        private readonly CaseRepository $repository,
        private readonly CreateCaseAction $createCase,
        private readonly UpdateCaseAction $updateCase,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function find(string $uuid): LegalCase
    {
        return $this->repository->findForDisplay($uuid);
    }

    public function create(CaseData $data): LegalCase
    {
        return $this->createCase->execute($data);
    }

    public function update(LegalCase $case, CaseData $data): LegalCase
    {
        return $this->updateCase->execute($case, $data);
    }

    public function delete(LegalCase $case): void
    {
        $this->repository->delete($case);
    }
}
