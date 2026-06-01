<?php

declare(strict_types=1);

namespace App\Http\Requests\Hearings;

use App\Enums\HearingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHearingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('hearing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'case_id' => ['sometimes', 'required', 'integer', Rule::exists('cases', 'id')->where('team_id', $teamId)],
            'scheduled_at' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::enum(HearingStatus::class)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'court_room' => ['nullable', 'string', 'max:120'],
            'judge_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:10000'],
            'next_hearing_at' => ['nullable', 'date'],
        ];
    }
}
