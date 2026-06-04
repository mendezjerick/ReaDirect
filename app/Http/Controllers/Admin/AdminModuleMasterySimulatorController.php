<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\ModuleMasterySimulatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminModuleMasterySimulatorController extends Controller
{
    public function index(Request $request, AdminAccessService $access, ModuleMasterySimulatorService $simulator): Response
    {
        $access->ensureTesting($request->user());

        return Inertia::render('Admin/Testing/ModuleMasterySimulator', [
            'learner' => $simulator->learnerPayload(),
            'result' => $simulator->latestResult(),
            'defaults' => [
                'task_1_score' => 10,
                'task_2_score' => 10,
                'task_3_score' => 10,
                'incorrect_words' => 0,
                'comprehension_correct_count' => 5,
            ],
            'routes' => [
                'simulate' => route('admin.testing.module-mastery-simulator.store'),
                'reset' => route('admin.testing.module-mastery-simulator.reset'),
            ],
        ]);
    }

    public function store(
        Request $request,
        AdminAccessService $access,
        ModuleMasterySimulatorService $simulator,
        AdminAuditService $audit
    ): RedirectResponse {
        $access->ensureTesting($request->user());

        $validated = $request->validate($this->rules());
        $result = $simulator->simulate($validated);
        $audit->log($request, 'admin.testing.module_mastery_simulated', null, [], [
            'learner_code' => ModuleMasterySimulatorService::LEARNER_CODE,
            'attempt_id' => $result['attempt']['id'] ?? null,
            'module_key' => $result['module']['key'] ?? null,
            'placement_decision' => $result['placement_decision']['decision'] ?? null,
        ]);

        return redirect()
            ->route('admin.testing.module-mastery-simulator.index')
            ->with('success', 'Module Mastery Simulator completed for MM.');
    }

    public function reset(
        Request $request,
        AdminAccessService $access,
        ModuleMasterySimulatorService $simulator,
        AdminAuditService $audit
    ): RedirectResponse {
        $access->ensureTesting($request->user());

        $simulator->reset();
        $audit->log($request, 'admin.testing.module_mastery_simulator_reset', null, [], [
            'learner_code' => ModuleMasterySimulatorService::LEARNER_CODE,
        ]);

        return redirect()
            ->route('admin.testing.module-mastery-simulator.index')
            ->with('success', 'MM has been reset to a blank simulator state.');
    }

    private function rules(): array
    {
        return [
            'task_1_score' => ['required', 'integer', 'min:0', 'max:10'],
            'task_2_score' => ['required', 'integer', 'min:0', 'max:10'],
            'task_3_score' => ['required', 'integer', 'min:0', 'max:10'],
            'incorrect_words' => ['required', 'integer', 'min:0', 'max:50'],
            'comprehension_correct_count' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }
}
