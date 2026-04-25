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
use App\Services\Admin\AdminTestingService;
use App\Services\AssessmentItemSelectionService;
use App\Services\ModuleActivitySelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTestingController extends Controller
{
    public function index(Request $request, AdminAccessService $access): Response
    {
        $access->ensureTesting($request->user());

        return Inertia::render('Admin/Testing/Index', [
            'learnersCount' => Learner::count(),
            'sandboxAssessments' => AssessmentAttempt::where('is_sandbox', true)->latest()->limit(10)->get(),
            'sandboxModules' => ModuleAttempt::with('module')->where('is_sandbox', true)->latest()->limit(10)->get(),
        ]);
    }

    public function learners(Request $request, AdminAccessService $access): Response
    {
        $access->ensureTesting($request->user());
        $search = $request->string('search')->toString();

        return Inertia::render('Admin/Testing/LearnerSelect', [
            'learners' => Learner::with(['school', 'schoolClass'])
                ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('learner_code', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function flowJump(Request $request, AdminAccessService $access, AdminTestingService $testing): Response
    {
        $access->ensureTesting($request->user());
        $learner = $request->session()->has('admin_testing_learner_id')
            ? Learner::find($request->session()->get('admin_testing_learner_id'))
            : null;

        return Inertia::render('Admin/Testing/FlowJump', [
            'learner' => $learner,
            'targets' => $learner ? $testing->jumpTargets($learner) : [],
            'modules' => Module::orderBy('sequence')->get(),
        ]);
    }

    public function startSandbox(Request $request, AdminAccessService $access, AdminTestingService $testing, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $validated = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learners,id'],
            'type' => ['required', 'in:diagnostic,module,final'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
        ]);
        $learner = Learner::findOrFail($validated['learner_id']);
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

    public function learnerJump(Request $request, Learner $learner, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $request->session()->put('admin_testing_mode', true);
        $request->session()->put('admin_testing_learner_id', $learner->id);
        $request->session()->put('learner_id', $learner->id);
        $audit->log($request, 'admin.testing.learner_jump_started', $learner);

        return redirect()->route('admin.testing.flow-jump');
    }

    public function jump(
        Request $request,
        string $target,
        AdminAccessService $access,
        AdminTestingService $testing,
        AdminAuditService $audit,
        AssessmentItemSelectionService $assessmentItems,
        ModuleActivitySelectionService $moduleItems
    ): RedirectResponse {
        $access->ensureTesting($request->user());
        $learner = Learner::findOrFail($request->session()->get('admin_testing_learner_id'));
        $request->session()->put('admin_testing_mode', true);
        $request->session()->put('learner_id', $learner->id);

        $audit->log($request, 'admin.testing.flow_jump', $learner, [], ['target' => $target]);

        if (str_starts_with($target, 'diagnostic-')) {
            return $this->jumpDiagnostic($request, $target, $learner, $testing, $assessmentItems);
        }

        if (str_starts_with($target, 'final-')) {
            return $this->jumpFinal($request, $target, $learner, $testing, $assessmentItems);
        }

        if (str_starts_with($target, 'module-')) {
            return $this->jumpModule($request, $target, $learner, $testing, $moduleItems);
        }

        return match ($target) {
            'learner-dashboard' => redirect()->route('learner.dashboard'),
            default => abort(404),
        };
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

    public function exit(Request $request, AdminAccessService $access, AdminAuditService $audit): RedirectResponse
    {
        $access->ensureTesting($request->user());
        $audit->log($request, 'admin.testing.exited');
        $request->session()->forget([
            'admin_testing_mode',
            'admin_testing_learner_id',
            'admin_testing_assessment_attempt_id',
            'admin_testing_module_attempt_id',
        ]);

        return redirect()->route('admin.testing.index')->with('success', 'Testing mode closed.');
    }

    private function jumpDiagnostic(Request $request, string $target, Learner $learner, AdminTestingService $testing, AssessmentItemSelectionService $items): RedirectResponse
    {
        $attempt = $testing->sandboxDiagnosticFor($learner, $request->user());
        $request->session()->put('assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);

        return match ($target) {
            'diagnostic-start' => redirect()->route('learner.diagnostic.start'),
            'diagnostic-task-1' => tap(redirect()->route('learner.diagnostic.task-1'), fn () => $items->selectTask1LettersForAttempt($attempt)),
            'diagnostic-task-2a' => tap(redirect()->route('learner.diagnostic.task-2a'), function () use ($attempt, $items): void {
                $attempt->update(['task_1_score' => 5, 'task_2a_score' => null, 'status' => 'task_1_completed']);
                $items->selectTask2ARhymingPromptsForAttempt($attempt);
            }),
            'diagnostic-task-2b' => tap(redirect()->route('learner.diagnostic.task-2b'), function () use ($attempt, $items): void {
                $attempt->update(['task_1_score' => $attempt->task_1_score ?? 8, 'task_2a_score' => $attempt->task_2a_score ?? 10]);
                $items->selectTask2BWordSentenceItemsForAttempt($attempt);
            }),
            'diagnostic-crla-summary' => tap(redirect()->route('learner.diagnostic.crla-summary'), fn () => $attempt->update([
                'task_1_score' => $attempt->task_1_score ?? 8,
                'task_2a_score' => $attempt->task_2a_score ?? 10,
                'task_2b_score' => $attempt->task_2b_score ?? 8,
                'crla_total_score' => $attempt->crla_total_score ?? 26,
                'crla_classification' => $attempt->crla_classification ?? 'Light Refresher',
            ])),
            'diagnostic-reading-intro' => redirect()->route('learner.diagnostic.reading-intro'),
            'diagnostic-passage' => tap(redirect()->route('learner.diagnostic.passage'), fn () => $items->selectReadingPassageForAttempt($attempt)),
            'diagnostic-comprehension' => tap(redirect()->route('learner.diagnostic.comprehension'), function () use ($attempt, $items): void {
                $items->selectReadingPassageForAttempt($attempt);
                $attempt->update(['incorrect_words' => $attempt->incorrect_words ?? 2, 'reading_accuracy' => $attempt->reading_accuracy ?? 96]);
            }),
            'diagnostic-reading-summary' => tap(redirect()->route('learner.diagnostic.reading-summary'), fn () => $attempt->update([
                'incorrect_words' => $attempt->incorrect_words ?? 2,
                'reading_accuracy' => $attempt->reading_accuracy ?? 96,
                'comprehension_correct_count' => $attempt->comprehension_correct_count ?? 4,
                'comprehension_percentage' => $attempt->comprehension_percentage ?? 80,
                'final_reading_score' => $attempt->final_reading_score ?? 86.4,
                'reading_classification' => $attempt->reading_classification ?? 'Transitioning Reader',
            ])),
            'diagnostic-module-placement' => tap(redirect()->route('learner.diagnostic.module-placement'), fn () => $attempt->update([
                'crla_classification' => $attempt->crla_classification ?? 'Grade Ready',
                'reading_classification' => $attempt->reading_classification ?? 'Developing Reader',
                'final_reading_score' => $attempt->final_reading_score ?? 70,
            ])),
            default => abort(404),
        };
    }

    private function jumpFinal(Request $request, string $target, Learner $learner, AdminTestingService $testing, AssessmentItemSelectionService $items): RedirectResponse
    {
        $attempt = $testing->sandboxFinalFor($learner, $request->user());
        $request->session()->put('final_assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);

        return match ($target) {
            'final-start' => redirect()->route('final-assessment.start'),
            'final-task-1' => tap(redirect()->route('final-assessment.task', 'task-1'), fn () => $items->selectTask1LettersForAttempt($attempt)),
            'final-task-2a' => tap(redirect()->route('final-assessment.task', 'task-2a'), function () use ($attempt, $items): void {
                $attempt->update(['task_1_score' => 5, 'task_2a_score' => null]);
                $items->selectTask2ARhymingPromptsForAttempt($attempt);
            }),
            'final-task-2b' => tap(redirect()->route('final-assessment.task', 'task-2b'), function () use ($attempt, $items): void {
                $attempt->update(['task_1_score' => $attempt->task_1_score ?? 8, 'task_2a_score' => $attempt->task_2a_score ?? 10]);
                $items->selectTask2BWordSentenceItemsForAttempt($attempt);
            }),
            'final-passage' => tap(redirect()->route('final-assessment.task', 'passage'), fn () => $items->selectReadingPassageForAttempt($attempt)),
            'final-comprehension' => tap(redirect()->route('final-assessment.task', 'comprehension'), function () use ($attempt, $items): void {
                $items->selectReadingPassageForAttempt($attempt);
                $attempt->update(['incorrect_words' => $attempt->incorrect_words ?? 2, 'reading_accuracy' => $attempt->reading_accuracy ?? 96]);
            }),
            'final-summary' => tap(redirect()->route('final-assessment.summary'), fn () => $attempt->update([
                'task_1_score' => $attempt->task_1_score ?? 8,
                'task_2a_score' => $attempt->task_2a_score ?? 10,
                'task_2b_score' => $attempt->task_2b_score ?? 8,
                'crla_total_score' => $attempt->crla_total_score ?? 26,
                'reading_accuracy' => $attempt->reading_accuracy ?? 96,
                'comprehension_percentage' => $attempt->comprehension_percentage ?? 80,
                'final_reading_score' => $attempt->final_reading_score ?? 86.4,
                'reading_classification' => $attempt->reading_classification ?? 'Transitioning Reader',
            ])),
            default => abort(404),
        };
    }

    private function jumpModule(Request $request, string $target, Learner $learner, AdminTestingService $testing, ModuleActivitySelectionService $items): RedirectResponse
    {
        preg_match('/^module-(module_[123])-(overview|activity|mastery|result|extra)$/', $target, $matches);
        abort_unless($matches, 404);

        $module = Module::where('key', $matches[1])->firstOrFail();
        $page = $matches[2];
        $attempt = $testing->sandboxModuleFor($learner, $module);
        $learner->update(['current_module_id' => $module->id, 'current_stage' => 'module_practice']);
        $request->session()->put('module_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_module_attempt_id', $attempt->id);

        $activityType = $items->practiceActivityTypes($module)[0] ?? 'mastery_check';

        return match ($page) {
            'overview' => redirect()->route('learner.modules.overview', $module),
            'activity' => redirect()->route('learner.modules.activity', [$module, $activityType]),
            'mastery' => redirect()->route('learner.modules.mastery-check', $module),
            'result' => tap(redirect()->route('learner.modules.mastery-result', $module), fn () => $attempt->update([
                'status' => 'completed',
                'score' => $attempt->score ?? 80,
                'mastery_decision' => $attempt->mastery_decision ?? 'repeat_'.$module->key,
                'rule_applied' => $attempt->rule_applied ?? strtoupper($module->key).'_MASTERY_SANDBOX',
            ])),
            'extra' => redirect()->route('learner.modules.extra-drills', $module),
            default => abort(404),
        };
    }
}
