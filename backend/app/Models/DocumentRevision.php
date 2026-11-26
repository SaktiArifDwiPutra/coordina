<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'borrow_document_id',
        'revision_number',
        'triggered_by_user_id',
        'reason',
        'snapshot_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function borrowDocument()
    {
        return $this->belongsTo(BorrowDocument::class);
    }
}

