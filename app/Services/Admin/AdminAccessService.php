<?php

namespace App\Services\Admin;

use App\Models\User;

class AdminAccessService
{
    public function ensureAdmin(?User $user): void
    {
        abort_unless($user?->hasAnyRole(['system_admin', 'school_admin']), 403);
    }

    public function ensureSystemAdmin(?User $user): void
    {
        abort_unless($user?->hasRole('system_admin'), 403);
    }

    public function canTest(?User $user): bool
    {
        return (bool) $user?->hasRole('system_admin');
    }

    public function ensureTesting(?User $user): void
    {
        abort_unless($this->canTest($user), 403);
    }
}
