<?php

namespace App\Models;

// 1. Pastikan baris ini ADA di deretan 'use' paling atas
use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    // 2. Pastikan 'HasApiTokens' ADA di dalam sini, bersanding dengan HasFactory
    use HasApiTokens, HasFactory, Notifiable; 

    protected $fillable = [
        'name',
        'email',
        'password',
        'year',
        'major',
        'class',
        'role',
        'organization_id',
        'must_change_password',
        'password_changed_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Automatically link admin_mpk accounts to the canonical MPK organization if unlinked.
     */
    public function ensureMpkOrganizationLinked(): ?int
    {
        if ($this->role !== 'admin_mpk') {
            return $this->organization_id;
        }

        if ($this->organization_id !== null) {
            $currentOrg = Organization::find($this->organization_id);
            if ($currentOrg && $currentOrg->is_mpk) {
                return $this->organization_id;
            }
        }

        $mpkOrg = Organization::resolveMpkOrganization();
        $this->forceFill(['organization_id' => $mpkOrg->id])->save();
        $this->setRelation('organization', $mpkOrg);

        return $mpkOrg->id;
    }
}