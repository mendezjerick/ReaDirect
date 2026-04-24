<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Services\LearnerProgressService;
use App\Services\TeacherAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAssessmentReviewController extends Controller
{
    public function show(
        Request $request,
        Learner $learner,
        AssessmentAttempt $assessmentAttempt,
        TeacherAccessService $access,
        LearnerProgressService $progress
    ): Response {
        $access->authorizeLearner($request->user(), $learner);
        abort_unless((int) $assessmentAttempt->learner_id === (int) $learner->id, 404);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'teacher.viewed_assessment_review',
            'auditable_type' => AssessmentAttempt::class,
            'auditable_id' => $assessmentAttempt->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Inertia::render('Teacher/AssessmentReview', [
            'learner' => [
                'public_id' => $learner->public_id,
                'name' => trim($learner->first_name.' '.$learner->last_name),
                'learner_code' => $learner->learner_code,
            ],
            ...$progress->assessmentReview($assessmentAttempt),
        ]);
    }
}
