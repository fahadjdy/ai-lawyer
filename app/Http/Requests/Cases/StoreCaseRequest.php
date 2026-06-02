<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use App\Models\LegalCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LegalCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'case_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('cases', 'case_number')->where('team_id', $teamId),
            ],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('team_id', $teamId)],
            'case_type' => ['required', Rule::enum(CaseType::class)],
            'status' => ['required', Rule::enum(CaseStatus::class)],
            'priority' => ['required', Rule::enum(CasePriority::class)],
            'favorability' => ['nullable', 'integer', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'court_type' => ['nullable', 'string', 'max:120'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'judge_name' => ['nullable', 'string', 'max:255'],
            'opposing_party' => ['nullable', 'string', 'max:255'],
            'opposing_counsel' => ['nullable', 'string', 'max:255'],
            'filing_date' => ['nullable', 'date'],
            'next_hearing_at' => ['nullable', 'date'],
            'lead_lawyer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('team_id', $teamId)],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('team_id', $teamId)],
        ];
    }
}
