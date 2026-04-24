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

        if (! $user->hasRole('teacher')) {
            return false;
        }

        return $user->teachingClasses()->whereKey($learner->class_id)->exists();
    }
}
