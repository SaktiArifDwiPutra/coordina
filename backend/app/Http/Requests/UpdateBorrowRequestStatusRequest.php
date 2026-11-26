<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin_mpk', 'owner_eskul'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending_mpk,pending_owner,approved,rejected'],
        ];
    }
}

