<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherAccessService;
use App\Services\TeacherDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function __invoke(Request $request, TeacherAccessService $access, TeacherDashboardService $dashboard): Response
    {
        $access->ensureTeacherArea($request->user());

        return Inertia::render('Teacher/Dashboard', [
            'dashboard' => $dashboard->summaryFor($request->user()),
        ]);
    }
}
