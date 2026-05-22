<?php

declare(strict_types=1);

namespace App\DTOs;

use App\DTOs\Concerns\FiltersNullValues;
use App\DTOs\Concerns\Optional;
use App\DTOs\Contracts\DataTransferObject;
use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use Illuminate\Http\Request;

/**
 * Immutable representation of a case create/update payload. Built from a
 * validated Form Request and consumed by case actions/services.
 */
final readonly class CaseData implements DataTransferObject
{
    use FiltersNullValues;

    /**
     * @param  array<int, string>|Optional  $tags
     * @param  array<int, int>|Optional  $assigneeIds
     */
    public function __construct(
        public string|Optional $title,
        public ?int $clientId,
        public CaseType|Optional $caseType,
        public CaseStatus|Optional $status,
        public CasePriority|Optional $priority,
        public ?string $caseNumber,
        public ?string $description,
        public ?string $courtName,
        public ?string $courtType,
        public ?string $jurisdiction,
        public ?string $judgeName,
        public ?string $opposingParty,
        public ?string $opposingCounsel,
        public ?string $filingDate,
        public ?string $nextHearingAt,
        public ?int $leadLawyerId,
        public array|Optional $tags,
        public array|Optional $assigneeIds,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $opt = Optional::create();

        return new self(
            title: $request->filled('title') ? (string) $request->string('title') : $opt,
            clientId: $request->integer('client_id') ?: null,
            caseType: $request->filled('case_type') ? CaseType::from($request->string('case_type')->value()) : $opt,
            status: $request->filled('status') ? CaseStatus::from($request->string('status')->value()) : $opt,
            priority: $request->filled('priority') ? CasePriority::from($request->string('priority')->value()) : $opt,
            caseNumber: $request->input('case_number'),
            description: $request->input('description'),
            courtName: $request->input('court_name'),
            courtType: $request->input('court_type'),
            jurisdiction: $request->input('jurisdiction'),
            judgeName: $request->input('judge_name'),
            opposingParty: $request->input('opposing_party'),
            opposingCounsel: $request->input('opposing_counsel'),
            filingDate: $request->input('filing_date'),
            nextHearingAt: $request->input('next_hearing_at'),
            leadLawyerId: $request->integer('lead_lawyer_id') ?: null,
            tags: $request->has('tags') ? (array) $request->input('tags', []) : $opt,
            assigneeIds: $request->has('assignee_ids') ? array_map('intval', (array) $request->input('assignee_ids', [])) : $opt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->withoutOptional([
            'title' => $this->title,
            'client_id' => $this->clientId,
            'case_type' => $this->caseType instanceof CaseType ? $this->caseType->value : $this->caseType,
            'status' => $this->status instanceof CaseStatus ? $this->status->value : $this->status,
            'priority' => $this->priority instanceof CasePriority ? $this->priority->value : $this->priority,
            'case_number' => $this->caseNumber,
            'description' => $this->description,
            'court_name' => $this->courtName,
            'court_type' => $this->courtType,
            'jurisdiction' => $this->jurisdiction,
            'judge_name' => $this->judgeName,
            'opposing_party' => $this->opposingParty,
            'opposing_counsel' => $this->opposingCounsel,
            'filing_date' => $this->filingDate,
            'next_hearing_at' => $this->nextHearingAt,
            'lead_lawyer_id' => $this->leadLawyerId,
            'tags' => $this->tags,
        ]);
    }

    /**
     * @return array<int, int>|null
     */
    public function assigneeIds(): ?array
    {
        return $this->assigneeIds instanceof Optional ? null : $this->assigneeIds;
    }
}
