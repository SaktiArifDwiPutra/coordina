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
        'is_mpk',
        'logo_path',
        'chairperson_name',
        'chairperson_title',
        'chairperson_id_number',
        'signature_image_path',
        'signature_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_mpk' => 'boolean',
            'signature_updated_at' => 'datetime',
        ];
    }

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

    public function isProfileCompleteForSigning(): bool
    {
        return ! empty($this->chairperson_name)
            && ! empty($this->chairperson_title)
            && ! empty($this->signature_image_path);
    }
}
