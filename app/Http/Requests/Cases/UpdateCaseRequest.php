<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Enums\CaseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;
        $caseId = $this->route('case')?->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'case_number' => [
                'sometimes', 'nullable', 'string', 'max:100',
                Rule::unique('cases', 'case_number')->where('team_id', $teamId)->ignore($caseId),
            ],
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('team_id', $teamId)],
            'case_type' => ['sometimes', 'required', Rule::enum(CaseType::class)],
            'status' => ['sometimes', 'required', Rule::enum(CaseStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(CasePriority::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'court_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'court_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'jurisdiction' => ['sometimes', 'nullable', 'string', 'max:120'],
            'judge_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'opposing_party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'opposing_counsel' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filing_date' => ['sometimes', 'nullable', 'date'],
            'next_hearing_at' => ['sometimes', 'nullable', 'date'],
            'lead_lawyer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('team_id', $teamId)],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'assignee_ids' => ['sometimes', 'nullable', 'array'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('team_id', $teamId)],
        ];
    }
}
