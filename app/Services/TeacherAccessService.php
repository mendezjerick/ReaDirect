<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class TeacherAccessService
{
    public function ensureTeacherArea(User $user): void
    {
        abort_unless($user->hasAnyRole(['teacher', 'school_admin', 'system_admin']), 403);
    }

    public function learnersFor(User $user): Builder
    {
        $query = Learner::query()
            ->with(['schoolClass', 'currentModule'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($user->hasRole('system_admin')) {
            return $query;
        }

        $classIds = $user->teachingClasses()->pluck('id');

        return $query->whereIn('class_id', $classIds);
    }

    public function authorizeLearner(User $user, Learner $learner): void
    {
        $this->ensureTeacherArea($user);

        Gate::forUser($user)->authorize('view', $learner);
    }
}
