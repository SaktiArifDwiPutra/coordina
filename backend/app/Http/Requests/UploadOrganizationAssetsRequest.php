<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrganizationAssetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin_mpk', 'owner_eskul'], true)
            && $this->user()?->organization_id !== null;
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'signature_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }
}

