<?php

namespace App\Policies;

use App\Models\AudioFile;
use App\Models\User;

class AudioFilePolicy
{
    public function view(User $user, AudioFile $audioFile): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if ($audioFile->learner?->user_id && (int) $audioFile->learner->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasAnyRole(['teacher', 'school_admin'])) {
            return $user->teachingClasses()->whereKey($audioFile->learner?->class_id)->exists();
        }

        return false;
    }
}
