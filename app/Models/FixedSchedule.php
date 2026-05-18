<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FixedSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Jadwal tetap ini milik eskul (organisasi) apa?
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}