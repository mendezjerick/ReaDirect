<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class AdminAIEnvironmentGuideController extends Controller
{
    public function __invoke(Request $request, AdminAccessService $access): Response
    {
        $access->ensureAdmin($request->user());

        $path = base_path('docs/ai-service/AI_ENVIRONMENT_GUIDE.md');

        return Inertia::render('Admin/AIEnvironmentGuide', [
            'guide' => File::exists($path) ? File::get($path) : '# AI Environment Guide not found',
        ]);
    }
}
