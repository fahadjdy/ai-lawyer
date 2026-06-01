<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use App\Enums\CaseStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only those who can edit the case may add tracking entries.
        return $this->user()->can('update', $this->route('case'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(CaseStage::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sections' => ['nullable', 'array', 'max:40'],
            'sections.*' => ['string', 'max:60'],
            'occurred_on' => ['nullable', 'date'],
        ];
    }
}
