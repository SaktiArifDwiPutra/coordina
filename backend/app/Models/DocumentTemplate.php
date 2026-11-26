<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'workflow_scope',
        'file_path',
        'placeholders_json',
        'version',
        'is_active',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'placeholders_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}

