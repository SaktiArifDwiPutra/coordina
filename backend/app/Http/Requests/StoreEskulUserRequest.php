<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEskulUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin_mpk';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }
}

