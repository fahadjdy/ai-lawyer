<?php

declare(strict_types=1);

namespace App\Http\Requests\Evidence;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Evidence::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'case_id' => ['required', 'integer', Rule::exists('cases', 'id')->where('team_id', $teamId)],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')->where('team_id', $teamId)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'status' => ['required', Rule::enum(EvidenceStatus::class)],
            'collected_at' => ['nullable', 'date'],
            'collected_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
