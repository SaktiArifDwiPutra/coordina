<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFixedScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin_mpk';
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:facilities,id'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'day' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:start_time'],
        ];
    }
}

