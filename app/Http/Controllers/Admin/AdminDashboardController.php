<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\ReadirectAIService;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminDashboardService;
use App\Services\Agents\AgentMediaModeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminAccessService $access, AdminDashboardService $dashboard, ReadirectAIService $ai, AgentMediaModeService $mediaMode): Response
    {
        $access->ensureAdmin($request->user());

        return Inertia::render('Admin/Dashboard', [
            'dashboard' => $dashboard->summary(),
            'aiService' => $ai->dashboardStatus(),
            'agentMediaMode' => $mediaMode->current(),
        ]);
    }

    public function aiStatus(Request $request, AdminAccessService $access, ReadirectAIService $ai): JsonResponse
    {
        $access->ensureAdmin($request->user());

        return response()->json($ai->dashboardStatus());
    }

    public function updateAgentMediaMode(Request $request, AdminAccessService $access, AgentMediaModeService $mediaMode): JsonResponse
    {
        $access->ensureAdmin($request->user());

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:chibi,dynamic'],
        ]);

        return response()->json([
            'mode' => $mediaMode->set($validated['mode'], $request->user()?->id),
        ]);
    }
}
