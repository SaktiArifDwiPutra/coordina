<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin_mpk', 'owner_eskul'], true);
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:start_time'],
            'reason' => ['required', 'string', 'max:1000'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'confirm_signature' => ['nullable', 'boolean'],
        ];
    }
}
