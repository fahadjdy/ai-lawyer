<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaseNoteRequest extends FormRequest
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
        return [
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }
}
