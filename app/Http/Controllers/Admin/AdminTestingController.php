<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LlmInteraction;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminDebugDataService;
use App\Services\Admin\AdminFilterOptionsService;
use App\Services\Admin\AdminTestingService;
use App\Services\Admin\QaTestingStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTestingController extends Controller
{
    public function index(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureTesting($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'attempt_type' => trim($request->string('attempt_type', 'all')->toString()) ?: 'all',
            'sandbox' => trim($request->string('sandbox', 'sandbox')->toString()) ?: 'sandbox',
            'status' => trim($request->string('status')->toString()),
            'module' => trim($request->string('module')->toString()),
            'date_from' => trim($request->string('date_from')->toString()),
            'date_to' => trim($request->string('date_to')->toString()),
        ];
        if (! in_array($filters['attempt_type'], ['all', 'diagnostic', 'final_reassessment', 'module'], true)) {
            $filters['attempt_type'] = 'all';
        }
        if (! in_array($filters['sandbox'], ['all', 'sandbox', 'live'], true)) {
            $filters['sandbox'] = 'sandbox';
        }
        $sandboxValue = match ($filters['sandbox']) {
            'sandbox' => true,
            'live' => false,
            default => null,
        };

        $assessmentQuery = AssessmentAttempt::with('learner')
            ->when($filters['attempt_type'] !== 'all' && $filters['attempt_type'] !== 'module', fn ($query) => $query->where('attempt_type', $filters['attempt_type']))
            ->when($filters['attempt_type'] === 'module', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($sandboxValue !== null, fn ($query) => $query->where('is_sandbox', $sandboxValue))
            ->when($filters['status'], fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'], fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'], fn ($query) => $query->whereHas('learner', fn ($learner) => $learner
                ->where('learner_code', 'like', "%{$filters['search']}%")
                ->orWhere('first_name', 'like', "%{$filters['search']}%")
                ->orWhere('last_name', 'like', "%{$filters['search']}%")));

        $moduleQuery = ModuleAttempt::with(['module', 'learner'])
            ->when($filters['attempt_type'] !== 'all' && $filters['attempt_type'] !== 'module', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($sandboxValue !== null, fn ($query) => $query->where('is_sandbox', $sandboxValue))
            ->when($filters['module'], fn ($query) => $query->whereHas('module', fn ($module) => $module->where('key', $filters['module'])))
            ->when($filters['status'], fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['date_from'], fn ($query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'], fn ($query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when($filters['search'], fn ($query) => $query->whereHas('learner', fn ($learner) => $learner
                ->where('learner_code', 'like', "%{$filters['search']}%")
                ->orWhere('first_name', 'like', "%{$filters['search']}%")
                ->orWhere('last_name', 'like', "%{$filters['search']}%")));

        return Inertia::render('Admin/Testing/Index', [
            'learnersCount' => Learner::whereNotIn('learner_code', [
                QaTestingStateService::LEARNER_CODE,
                \App\Services\Admin\ModuleMasterySimulatorService::LEARNER_CODE,
            ])->count(),
            'sandboxAssessments' => $assessmentQuery->latest()->limit(10)->get(),
            'sandboxModules' => $moduleQuery->latest()->limit(10)->get(),
            'filters' => $filters,
            'filterOptions' => [
                'modules' => $options->moduleOptions(),
                'attemptTypes' => [
                    ['label' => 'All attempt types', 'value' => 'all'],
                    ['label' => 'Diagnostic', 'value' => 'diagnostic'],
                    ['label' => 'Final reassessment', 'value' => 'final_reassessment'],
                    ['label' => 'Module attempt', 'value' => 'module'],
                ],
                'sandbox' => [
                    ['label' => 'Sandbox only', 'value' => 'sandbox'],
                    ['label' => 'Live only', 'value' => 'live'],
                    ['label' => 'Sandbox and live', 'value' => 'all'],
                ],
            ],
        ]);
    }

    public function learners(Request $request, AdminAccessService $access, AdminFilterOptionsService $options): Response
    {
        $access->ensureTesting($request->user());
        $filters = [
            'search' => trim($request->string('search')->toString()),
            'school_id' => trim($request->string('school_id')->toString()),
            'status' => $options->activeValue($request->string('status')->toString()),
        ];

        return Inertia::render('Admin/Testing/LearnerSelect', [
            'learners' => Learner::with(['school', 'schoolClass'])
                ->when($filters['school_id'], fn ($query) => $query->where('school_id', $filters['school_id']))
                ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
                ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
                ->when($filters['search'], fn ($query) => $query->where(fn ($inner) => $inner->where('learner_code', 'like', "%{$filters['search']}%")->orWhere('first_name', 'like', "%{$filters['search']}%")->orWhere('last_name', 'like', "%{$filters['search']}%")))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'filterOptions' => [
                'schools' => $options->schoolOptions(),
                'statuses' => $options->statusOptions(),
            ],
        ]);
    }

    public function flowJump(Request $request, AdminAccessService $access, AdminTestingService $testing, QaTestingStateService $qa): Response
    {
        $access->ensureTesting($request->user());
        $learner = $qa->activate($request);

        return Inertia::render('Admin/Testing/FlowJump', [
            'learner' => $learner,
            'targets' => $testing->jumpTargets($learner),
            'modules' => Module::orderBy('sequence')->get(),
        ]);
    }

    public function startSandbox(Request $request, AdminAccessService $access, AdminTestingService $testing, QaTestingStateService $qa, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $validated = $request->validate([
            'learner_id' => ['nullable', 'integer', 'exists:learners,id'],
            'type' => ['required', 'in:diagnostic,module,final'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
        ]);
        $qa->reset($request);
        $learner = $qa->activate($request);
        $request->session()->put('admin_testing_mode', true);
        $request->session()->put('admin_testing_learner_id', $learner->id);

        if ($validated['type'] === 'module') {
            $module = Module::findOrFail($validated['module_id'] ?? Module::orderBy('sequence')->value('id'));
            $attempt = $testing->startSandboxModule($learner, $module);
            $request->session()->put('module_attempt_id', $attempt->id);
            $request->session()->put('admin_testing_module_attempt_id', $attempt->id);
            $audit->log($request, 'admin.testing.sandbox_module_created', $attempt);
        } else {
            $attempt = $validated['type'] === 'final'
                ? $testing->startSandboxFinal($learner, $request->user())
                : $testing->startSandboxDiagnostic($learner, $request->user());
            $sessionKey = $validated['type'] === 'final' ? 'final_assessment_attempt_id' : 'assessment_attempt_id';
            $request->session()->put($sessionKey, $attempt->id);
            $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);
            $audit->log($request, 'admin.testing.sandbox_assessment_created', $attempt);
        }

        return redirect()->route('admin.testing.flow-jump')->with('success', 'Sandbox attempt created.');
    }

    public function learnerJump(Request $request, Learner $learner, AdminAccessService $access, QaTestingStateService $qa, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $tester = $qa->reset($request);
        $request->session()->put('admin_testing_mode', true);
        $request->session()->put('admin_testing_learner_id', $tester->id);
        $request->session()->put('learner_id', $tester->id);
        $audit->log($request, 'admin.testing.learner_jump_started', $tester, [], [
            'ignored_real_learner_id' => $learner->id,
            'qa_tester' => true,
        ]);

        return redirect()->route('admin.testing.flow-jump');
    }

    public function jump(
        Request $request,
        string $target,
        AdminAccessService $access,
        QaTestingStateService $qa,
        AdminAuditService $audit,
    ): RedirectResponse {
        $access->ensureTesting($request->user());
        $prepared = $qa->prepareJump($request, $target);
        $learner = $prepared['learner'];

        $audit->log($request, 'admin.testing.flow_jump', $learner, [], ['target' => $target]);

        return redirect()->to($prepared['redirect']);
    }

    public function assessmentDebug(Request $request, AssessmentAttempt $assessmentAttempt, AdminAccessService $access, AdminDebugDataService $debug, AdminAuditService $audit): Response
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.assessment_debug_viewed', $assessmentAttempt);

        return Inertia::render('Admin/Testing/AssessmentDebug', ['debug' => $debug->assessment($assessmentAttempt)]);
    }

    public function moduleDebug(Request $request, ModuleAttempt $moduleAttempt, AdminAccessService $access, AdminDebugDataService $debug, AdminAuditService $audit): Response
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.module_debug_viewed', $moduleAttempt);

        return Inertia::render('Admin/Testing/ModuleDebug', ['debug' => $debug->module($moduleAttempt)]);
    }

    public function sttDebug(Request $request, AudioFile $audioFile, AdminAccessService $access, AdminDebugDataService $debug, AdminAuditService $audit): Response
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.stt_debug_viewed', $audioFile);

        return Inertia::render('Admin/Testing/STTDebug', ['debug' => $debug->stt($audioFile)]);
    }

    public function llmDebug(Request $request, LlmInteraction $interaction, AdminAccessService $access, AdminDebugDataService $debug, AdminAuditService $audit): Response
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.llm_debug_viewed', $interaction);

        return Inertia::render('Admin/Testing/LLMDebug', ['debug' => $debug->llm($interaction)]);
    }

    public function resetTester(Request $request, AdminAccessService $access, QaTestingStateService $qa, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $tester = $qa->reset($request);
        $audit->log($request, 'admin.testing.tester_reset', $tester);

        return redirect()->route('admin.testing.flow-jump')->with('success', 'Tester QA state reset.');
    }

    public function exit(Request $request, AdminAccessService $access, QaTestingStateService $qa, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.exited');
        $qa->exit($request);

        return redirect()->route('admin.testing.index')->with('success', 'Testing mode closed.');
    }

}
