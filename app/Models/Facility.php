<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facility extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    // Fasilitas punya banyak jadwal tetap
    public function fixedSchedules()
    {
        return $this->hasMany(FixedSchedule::class);
    }

    public function borrowRequests()
    {
        return $this->hasMany(BorrowRequest::class);
    }
}