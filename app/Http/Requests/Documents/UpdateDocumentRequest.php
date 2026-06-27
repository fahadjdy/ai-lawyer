<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'case_id' => ['nullable', 'integer', Rule::exists('cases', 'id')->where('team_id', $teamId)],
            'folder_id' => ['nullable', 'integer', Rule::exists('document_folders', 'id')->where('team_id', $teamId)],
        ];
    }
}
