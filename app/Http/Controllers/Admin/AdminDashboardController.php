<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminAccessService $access, AdminDashboardService $dashboard): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Dashboard', [
            'dashboard' => $dashboard->summary(),
        ]);
    }
}
