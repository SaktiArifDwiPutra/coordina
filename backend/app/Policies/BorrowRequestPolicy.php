<?php

namespace App\Policies;

use App\Models\BorrowRequest;
use App\Models\User;

class BorrowRequestPolicy
{
    public function view(User $user, BorrowRequest $borrowRequest): bool
    {
        if ($user->role === 'admin_mpk') {
            return true;
        }

        if ($user->role !== 'owner_eskul' || $user->organization_id === null) {
            return false;
        }

        $orgId = (int) $user->organization_id;

        return (int) $borrowRequest->organization_id === $orgId
            || ($borrowRequest->owner_organization_id !== null && (int) $borrowRequest->owner_organization_id === $orgId);
    }

    public function screen(User $user, BorrowRequest $borrowRequest): bool
    {
        return $user->role === 'admin_mpk';
    }

    public function ownerDecision(User $user, BorrowRequest $borrowRequest): bool
    {
        return $user->role === 'owner_eskul'
            && $user->organization_id !== null
            && $borrowRequest->owner_organization_id !== null
            && (int) $borrowRequest->owner_organization_id === (int) $user->organization_id;
    }

    public function finalize(User $user, BorrowRequest $borrowRequest): bool
    {
        return $user->role === 'admin_mpk';
    }
}

