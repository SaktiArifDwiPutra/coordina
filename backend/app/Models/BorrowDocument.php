<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowDocument extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_WAITING_MPK_SCREENING = 'waiting_mpk_screening';
    public const STATUS_WAITING_MPK_SIGNATURE = 'waiting_mpk_signature';
    public const STATUS_WAITING_OWNER_SIGNATURE = 'waiting_owner_signature';
    public const STATUS_PENDING_MPK_VERIFICATION = 'pending_mpk_verification';
    public const STATUS_NEEDS_CORRECTION = 'needs_correction';
    public const STATUS_PENDING_PDF_GENERATION = 'pending_pdf_generation';
    public const STATUS_PDF_GENERATION_FAILED = 'pdf_generation_failed';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'borrow_request_id',
        'document_template_id',
        'current_revision',
        'document_status',
        'merged_docx_path',
        'final_pdf_path',
        'final_pdf_sha256',
        'pdf_generation_error',
        'verified_by_user_id',
        'verified_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function borrowRequest()
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function signatures()
    {
        return $this->hasMany(DocumentSignature::class)->orderBy('revision_number')->orderBy('stage_order');
    }

    public function activeSignatures()
    {
        return $this->hasMany(DocumentSignature::class)
            ->where('document_signatures.is_valid', true)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('borrow_documents')
                    ->whereColumn('borrow_documents.id', 'document_signatures.borrow_document_id')
                    ->whereColumn('borrow_documents.current_revision', 'document_signatures.revision_number');
            })
            ->orderBy('document_signatures.stage_order');
    }

    public function revisions()
    {
        return $this->hasMany(DocumentRevision::class)->orderBy('revision_number');
    }
}

