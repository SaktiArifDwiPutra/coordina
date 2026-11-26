<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin_mpk', 'owner_eskul'], true)
            && $this->user()?->organization_id !== null;
    }

    public function rules(): array
    {
        return [
            'advisor_name' => ['nullable', 'string', 'max:255'],
            'chairperson_name' => ['required', 'string', 'max:255'],
            'chairperson_title' => ['required', 'string', 'max:255'],
            'chairperson_id_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}

