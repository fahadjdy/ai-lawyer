<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Document::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $teamId = $this->user()->team_id;

        return [
            'file' => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,csv,jpg,jpeg,png,gif,webp,heic,mp3,wav,m4a,ogg,mp4,mov,avi,mkv,zip'],
            'name' => ['nullable', 'string', 'max:255'],
            'case_id' => ['nullable', 'integer', Rule::exists('cases', 'id')->where('team_id', $teamId)],
            'folder_id' => ['nullable', 'integer', Rule::exists('document_folders', 'id')->where('team_id', $teamId)],
        ];
    }
}
