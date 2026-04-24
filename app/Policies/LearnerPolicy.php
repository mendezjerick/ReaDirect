<?php

namespace App\Policies;

use App\Models\Learner;
use App\Models\User;

class LearnerPolicy
{
    public function view(User $user, Learner $learner): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if ($user->hasRole('student')) {
            return $learner->user_id === $user->id;
        }

        if ($user->hasAnyRole(['teacher', 'school_admin'])) {
            return $learner->schoolClass?->teacher_id === $user->id || $user->hasRole('school_admin');
        }

        return false;
    }
}
