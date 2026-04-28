<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use Inertia\Inertia;
use Inertia\Response;

class LearnerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $learner = Learner::with('currentModule')->find(session('learner_id')) ?? Learner::with('currentModule')->first();
        $latestAttempt = $learner?->assessmentAttempts()
            ->latest('id')
            ->first([
                'id',
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'assigned_module_id',
            ]);

        return Inertia::render('Learner/Dashboard', [
            'learner' => $learner ? array_merge(
                $learner->only('id', 'public_id', 'first_name', 'learner_code', 'current_module_id'),
                [
                    'current_module' => $learner->currentModule?->only('id', 'key', 'sequence', 'title', 'description'),
                ]
            ) : null,
            'modules' => Module::query()->orderBy('sequence')->get(['key', 'sequence', 'title', 'description']),
            'latestAttempt' => $latestAttempt?->only(
                'id',
                'status',
                'task_1_score',
                'task_2a_score',
                'task_2b_score',
                'crla_total_score',
                'reading_accuracy',
                'final_reading_score',
                'assigned_module_id',
            ),
        ]);
    }
}
