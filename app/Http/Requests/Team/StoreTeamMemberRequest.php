<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\RoleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('team.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'designation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(RoleType::class)],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
