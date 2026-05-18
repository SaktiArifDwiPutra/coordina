<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BorrowRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'organization_id', 'facility_id', 'owner_organization_id', 
        'date', 'start_time', 'end_time', 'reason', 'status'
    ];

    // 1. Relasi ke Eskul Peminjam
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // 2. Relasi ke Fasilitas yang dipinjam
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    // 3. Relasi ke Eskul Pemilik Jadwal
    public function ownerOrganization()
    {
        return $this->belongsTo(Organization::class, 'owner_organization_id');
    }
}