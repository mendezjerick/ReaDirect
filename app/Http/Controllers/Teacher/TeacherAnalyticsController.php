<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ClassAnalyticsService;
use App\Services\TeacherAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAnalyticsController extends Controller
{
    public function __invoke(Request $request, TeacherAccessService $access, ClassAnalyticsService $analytics): Response
    {
        $access->ensureTeacherArea($request->user());

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'teacher.viewed_class_analytics',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Inertia::render('Teacher/ClassAnalytics', [
            'analytics' => $analytics->analyticsFor($request->user()),
        ]);
    }
}
