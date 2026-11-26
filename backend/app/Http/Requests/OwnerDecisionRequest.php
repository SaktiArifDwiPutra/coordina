<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OwnerDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'owner_eskul';
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:1000'],
            'confirm_signature' => ['nullable', 'boolean'],
        ];
    }
}

