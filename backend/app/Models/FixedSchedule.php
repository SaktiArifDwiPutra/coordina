<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FixedSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'facility_id',
        'day',
        'start_time',
        'end_time',
    ];

    // Jadwal tetap ini milik eskul (organisasi) apa?
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }
}