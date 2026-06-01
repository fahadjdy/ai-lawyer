<?php

declare(strict_types=1);

namespace App\Http\Requests\Hearings;

use App\Enums\HearingStatus;
use App\Models\Hearing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHearingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Hearing::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'case_id' => ['required', 'integer', Rule::exists('cases', 'id')->where('team_id', $teamId)],
            'scheduled_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(HearingStatus::class)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'court_room' => ['nullable', 'string', 'max:120'],
            'judge_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:10000'],
            'next_hearing_at' => ['nullable', 'date'],
        ];
    }
}
