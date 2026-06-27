<?php

declare(strict_types=1);

namespace App\Http\Requests\Evidence;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustodyEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evidence'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:120'],
            'handler' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }
}
