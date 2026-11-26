<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin_mpk';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'workflow_scope' => ['nullable', 'in:shared,workflow_a,workflow_b'],
            'template' => ['required', 'file', 'mimes:docx', 'max:5120'],
        ];
    }
}

