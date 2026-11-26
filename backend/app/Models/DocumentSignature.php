<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'borrow_document_id',
        'revision_number',
        'signer_role',
        'stage_order',
        'organization_id',
        'signed_by_user_id',
        'signer_name_snapshot',
        'signer_title_snapshot',
        'signer_id_number_snapshot',
        'signature_image_path_snapshot',
        'is_valid',
        'signed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'signed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function borrowDocument()
    {
        return $this->belongsTo(BorrowDocument::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function signedBy()
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}

