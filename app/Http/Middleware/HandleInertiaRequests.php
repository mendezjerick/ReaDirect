<?php

namespace App\Http\Middleware;

use App\Services\Agents\AgentMediaModeService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        if (file_exists($manifest = public_path('build/manifest.json'))) {
            return md5_file($manifest);
        }

        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only('public_id', 'name', 'email'),
                'roles' => $request->user()?->getRoleNames() ?? [],
            ],
            'csrf_token' => csrf_token(),
            'adminTesting' => [
                'enabled' => (bool) ($request->user()?->hasRole('system_admin') && $request->session()->get('admin_testing_mode')),
                'learner_id' => $request->session()->get('admin_testing_learner_id'),
                'assessment_attempt_id' => $request->session()->get('admin_testing_assessment_attempt_id'),
                'module_attempt_id' => $request->session()->get('admin_testing_module_attempt_id'),
            ],
            'agentMedia' => [
                'mode' => app(AgentMediaModeService::class)->current(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
