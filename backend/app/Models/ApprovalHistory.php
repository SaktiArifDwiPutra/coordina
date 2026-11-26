<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'borrow_request_id',
        'actor_user_id',
        'actor_role',
        'action',
        'from_status',
        'to_status',
        'note',
        'metadata_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function borrowRequest()
    {
        return $this->belongsTo(BorrowRequest::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

