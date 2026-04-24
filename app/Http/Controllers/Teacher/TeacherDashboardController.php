<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Learner;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'learners' => Learner::count(),
                'assessments' => AssessmentAttempt::count(),
                'needs_review' => AssessmentAttempt::where('status', 'completed')->count(),
            ],
        ]);
    }
}
