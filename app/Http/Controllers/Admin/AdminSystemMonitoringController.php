<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\SystemMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSystemMonitoringController extends Controller
{
    public function __invoke(Request $request, AdminAccessService $access, SystemMonitoringService $system): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/SystemMonitoring/Index', ['system' => $system->summary()]);
    }
}
