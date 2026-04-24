<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Services\LearnerProgressService;
use App\Services\TeacherAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherModuleProgressController extends Controller
{
    public function index(Request $request, Learner $learner, TeacherAccessService $access, LearnerProgressService $progress): Response
    {
        $access->authorizeLearner($request->user(), $learner);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'teacher.viewed_module_progress',
            'auditable_type' => Learner::class,
            'auditable_id' => $learner->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Inertia::render('Teacher/ModuleProgressReview', $progress->moduleReview($learner));
    }
}
