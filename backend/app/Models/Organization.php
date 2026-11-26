<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'advisor_name',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function fixedSchedules()
    {
        return $this->hasMany(FixedSchedule::class);
    }

    public function borrowRequests()
    {
        return $this->hasMany(BorrowRequest::class);
    }
}
