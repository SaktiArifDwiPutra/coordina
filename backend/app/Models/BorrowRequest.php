<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BorrowRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING_MPK_SCREENING = 'pending_mpk_screening';
    public const STATUS_PENDING_OWNER = 'pending_owner';
    public const STATUS_OWNER_APPROVED = 'owner_approved';
    public const STATUS_OWNER_REJECTED = 'owner_rejected';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const WORKFLOW_INTER_ESKUL = 'inter_eskul';
    public const WORKFLOW_MPK_BORROW = 'mpk_borrow';
    public const WORKFLOW_FREE_SLOT = 'free_slot';

    protected $fillable = [
        'user_id',
        'organization_id',
        'facility_id',
        'owner_organization_id',
        'workflow_type',
        'date',
        'start_time',
        'end_time',
        'reason',
        'priority',
        'status',
        'rejection_reason',
        'mpk_screened_at',
        'owner_decided_at',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'mpk_screened_at' => 'datetime',
            'owner_decided_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function ownerOrganization()
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }

    public function histories()
    {
        return $this->hasMany(ApprovalHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
