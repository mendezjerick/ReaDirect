<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Support\CurrentLearner;
use App\Support\LearnerStage;
use Illuminate\Http\Request;

class LearnerFlowService
{
    public const DIAGNOSTIC_COMPLETE = 'module_placement_completed';

    public const FINAL_COMPLETE = 'final_reassessment_completed';

    public const ADVANCED_MODULE_KEY = 'advanced_module';

    public function learner(Request $request): Learner
    {
        return CurrentLearner::require($request, true);
    }

    public function state(Learner $learner): array
    {
        $learner->loadMissing('currentModule');

        $diagnostic = $this->latestDiagnosticAttempt($learner);
        $activeDiagnostic = $this->activeDiagnosticAttempt($learner);
        $final = $this->latestFinalAttempt($learner);
        $activeFinal = $this->activeFinalAttempt($learner);
        $moduleAttempt = $learner->current_module_id
            ? $this->activeModuleAttempt($learner, $learner->currentModule)
            : null;
        $stage = $this->effectiveStage($learner, $diagnostic, $activeDiagnostic, $final, $activeFinal, $moduleAttempt);
        $primary = $this->primaryAction($learner, $stage, $activeDiagnostic, $diagnostic, $activeFinal, $final, $moduleAttempt);

        return [
            'stage' => $stage,
            'primary_action_label' => $primary['label'],
            'primary_action_route' => $primary['route'],
            'current_module_id' => $learner->current_module_id,
            'current_module_key' => $learner->currentModule?->key,
            'message' => $primary['message'],
            'diagnostic' => [
                'has_attempt' => (bool) $diagnostic,
                'attempt_id' => $diagnostic?->id,
                'status' => $diagnostic?->status,
                'resume_route' => $activeDiagnostic ? $this->diagnosticResumeRoute($activeDiagnostic) : null,
                'is_completed' => $this->isDiagnosticComplete($diagnostic),
            ],
            'module' => [
                'has_current_module' => (bool) $learner->current_module_id,
                'current_module_id' => $learner->current_module_id,
                'current_module_key' => $learner->currentModule?->key,
                'active_attempt_id' => $moduleAttempt?->id,
                'resume_route' => $learner->currentModule ? $this->moduleResumeRoute($learner, $learner->currentModule) : null,
            ],
            'final_reassessment' => [
                'has_attempt' => (bool) $final,
                'attempt_id' => $final?->id,
                'status' => $final?->status,
                'resume_route' => $activeFinal ? $this->finalResumeRoute($activeFinal) : null,
                'is_completed' => $this->isFinalComplete($final),
            ],
        ];
    }

    public function effectiveStage(
        Learner $learner,
        ?AssessmentAttempt $diagnostic = null,
        ?AssessmentAttempt $activeDiagnostic = null,
        ?AssessmentAttempt $final = null,
        ?AssessmentAttempt $activeFinal = null,
        ?ModuleAttempt $moduleAttempt = null
    ): string {
        $diagnostic ??= $this->latestDiagnosticAttempt($learner);
        $activeDiagnostic ??= $this->activeDiagnosticAttempt($learner);
        $final ??= $this->latestFinalAttempt($learner);
        $activeFinal ??= $this->activeFinalAttempt($learner);
        $moduleAttempt ??= $learner->current_module_id ? $this->activeModuleAttempt($learner, $learner->currentModule) : null;

        $stage = LearnerStage::normalize($learner->current_stage);

        if ($stage === LearnerStage::COMPLETED) {
            return LearnerStage::COMPLETED;
        }

        if ($stage === LearnerStage::FINAL_REASSESSMENT_COMPLETED || $this->isFinalComplete($final)) {
            return LearnerStage::FINAL_REASSESSMENT_COMPLETED;
        }

        if ($activeFinal) {
            return LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS;
        }

        if ($stage === LearnerStage::EXTRA_PHONEME_DRILLS && $learner->current_module_id) {
            return LearnerStage::MODULE_ASSIGNED;
        }

        if ($activeDiagnostic) {
            return LearnerStage::DIAGNOSTIC_IN_PROGRESS;
        }

        if (in_array($stage, [
            LearnerStage::MODULE_ASSIGNED,
            LearnerStage::MODULE_PRACTICE_IN_PROGRESS,
            LearnerStage::MODULE_MASTERY_IN_PROGRESS,
            LearnerStage::FINAL_REASSESSMENT_PENDING,
            LearnerStage::GRADE_READY,
            LearnerStage::COMPLETED,
        ], true)) {
            if ($stage === LearnerStage::MODULE_MASTERY_IN_PROGRESS || $moduleAttempt?->status === 'mastery_started') {
                return LearnerStage::MODULE_MASTERY_IN_PROGRESS;
            }

            if ($stage === LearnerStage::MODULE_PRACTICE_IN_PROGRESS || ($moduleAttempt && in_array($moduleAttempt->status, ['in_progress', 'practice_started'], true))) {
                return $stage === LearnerStage::MODULE_ASSIGNED
                    ? LearnerStage::MODULE_PRACTICE_IN_PROGRESS
                    : $stage;
            }

            return $stage;
        }

        if (! $this->isDiagnosticComplete($diagnostic)) {
            return LearnerStage::normalize($learner->current_stage) === LearnerStage::DIAGNOSTIC_IN_PROGRESS
                ? LearnerStage::DIAGNOSTIC_IN_PROGRESS
                : LearnerStage::NEW;
        }

        if ($stage === LearnerStage::FINAL_REASSESSMENT_PENDING) {
            return $stage;
        }

        if ($stage === LearnerStage::GRADE_READY || (! $learner->current_module_id && ! $diagnostic?->assigned_module_id)) {
            return LearnerStage::GRADE_READY;
        }

        if ($moduleAttempt?->status === 'mastery_started') {
            return LearnerStage::MODULE_MASTERY_IN_PROGRESS;
        }

        if ($moduleAttempt && in_array($moduleAttempt->status, ['in_progress', 'practice_started'], true)) {
            return LearnerStage::MODULE_PRACTICE_IN_PROGRESS;
        }

        return $learner->current_module_id ? LearnerStage::MODULE_ASSIGNED : $stage;
    }

    public function resolveDiagnosticAttempt(Request $request, bool $allowCompleted = false): ?AssessmentAttempt
    {
        $learner = $this->learner($request);
        $sessionAttempt = AssessmentAttempt::with('selectedItems')
            ->where('id', $request->session()->get('assessment_attempt_id'))
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->first();

        if ($sessionAttempt && ($allowCompleted || ! $this->isDiagnosticComplete($sessionAttempt))) {
            return $sessionAttempt;
        }

        $attempt = $this->activeDiagnosticAttempt($learner);

        if ($attempt) {
            $request->session()->put('assessment_attempt_id', $attempt->id);
        }

        return $attempt;
    }

    public function resolveFinalAttempt(Request $request, bool $allowCompleted = false): ?AssessmentAttempt
    {
        $learner = $this->learner($request);
        $sessionAttempt = AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])
            ->where('id', $request->session()->get('final_assessment_attempt_id'))
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->first();

        if ($sessionAttempt && ($allowCompleted || ! $this->isFinalComplete($sessionAttempt))) {
            return $sessionAttempt;
        }

        $attempt = $this->activeFinalAttempt($learner);

        if ($attempt) {
            $request->session()->put('final_assessment_attempt_id', $attempt->id);
        }

        return $attempt;
    }

    public function resolveModuleAttempt(Request $request, Learner $learner, Module $module): ?ModuleAttempt
    {
        $attempt = ModuleAttempt::query()
            ->where('id', $request->session()->get('module_attempt_id'))
            ->where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->whereIn('status', ['in_progress', 'practice_started', 'mastery_started'])
            ->first();

        $attempt ??= $this->activeModuleAttempt($learner, $module);

        if ($attempt) {
            $request->session()->put('module_attempt_id', $attempt->id);
        }

        return $attempt;
    }

    public function activeDiagnosticAttempt(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::with('selectedItems')
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->whereNull('completed_at')
            ->where('status', '!=', self::DIAGNOSTIC_COMPLETE)
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function latestDiagnosticAttempt(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::query()
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function activeFinalAttempt(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::with(['selectedItems', 'baselineAssessment'])
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->whereNull('completed_at')
            ->where('status', '!=', self::FINAL_COMPLETE)
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function latestFinalAttempt(Learner $learner): ?AssessmentAttempt
    {
        return AssessmentAttempt::query()
            ->where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function activeModuleAttempt(Learner $learner, ?Module $module): ?ModuleAttempt
    {
        if (! $module) {
            return null;
        }

        return ModuleAttempt::query()
            ->where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->whereIn('status', ['in_progress', 'practice_started', 'mastery_started'])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    public function isDiagnosticComplete(?AssessmentAttempt $attempt): bool
    {
        return (bool) $attempt
            && $attempt->attempt_type === 'diagnostic'
            && $attempt->status === self::DIAGNOSTIC_COMPLETE
            && $attempt->completed_at !== null;
    }

    public function isFinalComplete(?AssessmentAttempt $attempt): bool
    {
        return (bool) $attempt
            && $attempt->attempt_type === 'final_reassessment'
            && $attempt->status === self::FINAL_COMPLETE
            && $attempt->completed_at !== null;
    }

    public function isAdvancedModule(Module $module): bool
    {
        return $module->key === self::ADVANCED_MODULE_KEY;
    }

    public function advancedModuleUnlocked(Learner $learner): bool
    {
        return $this->isPerfectFinalAttempt($this->latestFinalAttempt($learner));
    }

    public function isPerfectFinalAttempt(?AssessmentAttempt $attempt): bool
    {
        if (! $this->isFinalComplete($attempt)) {
            return false;
        }

        return (int) $attempt->task_1_score === 10
            && (int) $attempt->task_2a_score === 10
            && (int) $attempt->task_2b_score === 10
            && (int) $attempt->crla_total_score === 30
            && round((float) $attempt->reading_accuracy, 2) >= 100.0
            && round((float) $attempt->comprehension_percentage, 2) >= 100.0
            && round((float) $attempt->final_reading_score, 2) >= 100.0;
    }

    public function diagnosticResumeRoute(AssessmentAttempt $attempt): string
    {
        return route($this->diagnosticResumeRouteName($attempt));
    }

    public function diagnosticResumeRouteName(AssessmentAttempt $attempt): string
    {
        if ($attempt->task_1_score === null) {
            return 'learner.diagnostic.task-1';
        }

        if ((int) $attempt->task_1_score <= 6) {
            if ($attempt->task_2a_score === null) {
                return 'learner.diagnostic.task-2a';
            }

            if ($attempt->crla_total_score === null || $attempt->final_reading_score === null) {
                return 'learner.diagnostic.crla-summary';
            }

            if (! $this->isDiagnosticComplete($attempt)) {
                return 'learner.diagnostic.module-placement';
            }

            return 'learner.dashboard';
        }

        if ($attempt->task_2b_score === null) {
            return 'learner.diagnostic.task-2b';
        }

        if ($attempt->crla_total_score === null) {
            return 'learner.diagnostic.task-2b';
        }

        if (! $this->passageEligibleForAttempt($attempt)) {
            return 'learner.diagnostic.module-placement';
        }

        if ($attempt->reading_accuracy === null && ! $this->hasSelectedReadingPassage($attempt)) {
            return 'learner.diagnostic.story-selection';
        }

        if ($attempt->reading_accuracy === null) {
            return 'learner.diagnostic.passage';
        }

        if ($attempt->final_reading_score === null) {
            return 'learner.diagnostic.comprehension';
        }

        if (! $this->isDiagnosticComplete($attempt)) {
            return 'learner.diagnostic.module-placement';
        }

        return 'learner.dashboard';
    }

    public function diagnosticRouteNameForStep(string $step): string
    {
        return match ($step) {
            'task-1' => 'learner.diagnostic.task-1',
            'task-routing' => 'learner.diagnostic.task-routing',
            'task-2a' => 'learner.diagnostic.task-2a',
            'task-2a-summary' => 'learner.diagnostic.task-2a-summary',
            'task-2b' => 'learner.diagnostic.task-2b',
            'crla-summary' => 'learner.diagnostic.crla-summary',
            'reading-intro' => 'learner.diagnostic.reading-intro',
            'story-selection' => 'learner.diagnostic.story-selection',
            'passage' => 'learner.diagnostic.passage',
            'comprehension' => 'learner.diagnostic.comprehension',
            'reading-summary' => 'learner.diagnostic.reading-summary',
            'module-placement' => 'learner.diagnostic.module-placement',
            default => 'learner.dashboard',
        };
    }

    public function diagnosticStepAllowed(AssessmentAttempt $attempt, string $step): bool
    {
        $current = $this->diagnosticResumeRouteName($attempt);

        return match ($step) {
            'task-1' => $current === 'learner.diagnostic.task-1',
            'task-routing' => $attempt->task_1_score !== null && $attempt->task_2b_score === null,
            'task-2a' => $current === 'learner.diagnostic.task-2a',
            'task-2a-summary' => (int) $attempt->task_1_score <= 6 && $attempt->task_2a_score !== null && $attempt->crla_total_score !== null,
            'task-2b' => $current === 'learner.diagnostic.task-2b' && (int) $attempt->task_1_score >= 7,
            'crla-summary' => $attempt->crla_total_score !== null && ! $this->isDiagnosticComplete($attempt),
            'reading-intro' => $attempt->crla_total_score !== null && $this->passageEligibleForAttempt($attempt) && $attempt->reading_accuracy === null,
            'story-selection' => $current === 'learner.diagnostic.story-selection',
            'passage' => $current === 'learner.diagnostic.passage',
            'comprehension' => $current === 'learner.diagnostic.comprehension',
            'reading-summary' => $attempt->final_reading_score !== null && ! $this->isDiagnosticComplete($attempt),
            'module-placement' => $current === 'learner.diagnostic.module-placement',
            default => false,
        };
    }

    public function finalResumeRoute(AssessmentAttempt $attempt): string
    {
        $taskKey = $this->finalResumeTaskKey($attempt);

        return $taskKey === 'summary'
            ? route('final-assessment.summary')
            : route('final-assessment.task', $taskKey);
    }

    public function finalResumeTaskKey(AssessmentAttempt $attempt): string
    {
        if ($attempt->task_1_score === null) {
            return 'task-1';
        }

        if ((int) $attempt->task_1_score <= 6) {
            return $attempt->task_2a_score === null ? 'task-2a' : 'summary';
        }

        if ($attempt->task_2b_score === null || $attempt->crla_total_score === null) {
            return 'task-2b';
        }

        if (! $this->passageEligibleForAttempt($attempt)) {
            return 'summary';
        }

        if ($attempt->reading_accuracy === null && ! $this->hasSelectedReadingPassage($attempt)) {
            return 'story-selection';
        }

        if ($attempt->reading_accuracy === null) {
            return 'passage';
        }

        if ($attempt->final_reading_score === null) {
            return 'comprehension';
        }

        return 'summary';
    }

    public function finalTaskAllowed(AssessmentAttempt $attempt, string $taskKey): bool
    {
        return $taskKey === $this->finalResumeTaskKey($attempt);
    }

    private function passageEligibleForAttempt(AssessmentAttempt $attempt): bool
    {
        if ($attempt->task_1_score === null || $attempt->crla_total_score === null) {
            return false;
        }

        return app(CrlaScoringService::class)->shouldAdministerPassage((int) $attempt->task_1_score, (int) $attempt->crla_total_score);
    }

    private function hasSelectedReadingPassage(AssessmentAttempt $attempt): bool
    {
        if ($attempt->relationLoaded('selectedItems')) {
            return $attempt->selectedItems
                ->contains(fn ($item): bool => $item->task_type === AssessmentItemSelectionService::READING_PASSAGE);
        }

        return $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::READING_PASSAGE)
            ->exists();
    }

    public function moduleAccessible(Learner $learner, Module $module): bool
    {
        if ($this->isAdvancedModule($module)) {
            return $this->advancedModuleUnlocked($learner);
        }

        return $learner->current_module_id !== null
            && (int) $learner->current_module_id === (int) $module->id
            && ! in_array(LearnerStage::normalize($learner->current_stage), [
                LearnerStage::GRADE_READY,
                LearnerStage::FINAL_REASSESSMENT_PENDING,
                LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS,
                LearnerStage::FINAL_REASSESSMENT_COMPLETED,
                LearnerStage::COMPLETED,
            ], true);
    }

    public function moduleResumeRoute(Learner $learner, Module $module): string
    {
        $attempt = $this->activeModuleAttempt($learner, $module);

        if ($attempt?->status === 'mastery_started') {
            return route('learner.modules.mastery-check', $module);
        }

        if ($attempt) {
            $activity = $this->nextPracticeActivity($attempt, $module);

            return $activity
                ? route('learner.modules.activity', [$module, $activity])
                : route('learner.modules.mastery-check', $module);
        }

        return route('learner.modules.start', $module);
    }

    public function nextPracticeActivity(ModuleAttempt $attempt, Module $module): ?string
    {
        $selection = app(ModuleActivitySelectionService::class);
        $activityTypes = $selection->practiceActivityTypes($module);

        foreach ($activityTypes as $activityType) {
            if ($selection->lessonMastered($attempt, $activityType)) {
                continue;
            }

            $progress = $selection->latestLessonProgress($attempt, $activityType);

            if (! $progress || $progress->status === 'retry') {
                return $activityType;
            }

            $items = $selection->currentPracticeItemsForAttempt($attempt, $activityType);

            if ($items->isEmpty()
                || $progress->status === 'in_progress'
                || $items->contains(fn ($item) => $item->answered_at === null)
            ) {
                return $activityType;
            }
        }

        return null;
    }

    private function primaryAction(
        Learner $learner,
        string $stage,
        ?AssessmentAttempt $activeDiagnostic,
        ?AssessmentAttempt $diagnostic,
        ?AssessmentAttempt $activeFinal,
        ?AssessmentAttempt $final,
        ?ModuleAttempt $moduleAttempt
    ): array {
        return match ($stage) {
            LearnerStage::NEW => $this->action('Start Diagnostic', route('learner.diagnostic.start'), 'Begin with your diagnostic reading check.'),
            LearnerStage::DIAGNOSTIC_IN_PROGRESS => $this->action('Continue Diagnostic', $activeDiagnostic ? $this->diagnosticResumeRoute($activeDiagnostic) : route('learner.diagnostic.start'), 'Continue the diagnostic from your saved step.'),
            LearnerStage::MODULE_ASSIGNED => $this->action('Start Module', $learner->currentModule ? route('learner.modules.start', $learner->currentModule) : route('learner.dashboard'), 'Your current module is ready.'),
            LearnerStage::MODULE_PRACTICE_IN_PROGRESS => $this->action('Continue Module', $learner->currentModule ? $this->moduleResumeRoute($learner, $learner->currentModule) : route('learner.dashboard'), 'Continue your current module practice.'),
            LearnerStage::MODULE_MASTERY_IN_PROGRESS => $this->action('Continue Mastery Check', $learner->currentModule ? route('learner.modules.mastery-check', $learner->currentModule) : route('learner.dashboard'), 'Continue your mastery check.'),
            LearnerStage::FINAL_REASSESSMENT_PENDING => $this->action('Start Final Reassessment', route('final-assessment.start'), 'You are ready for the final reassessment.'),
            LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS => $this->action('Continue Final Reassessment', $activeFinal ? $this->finalResumeRoute($activeFinal) : route('final-assessment.start'), 'Continue the final reassessment from your saved step.'),
            LearnerStage::FINAL_REASSESSMENT_COMPLETED => $this->action('View Completion', route('learner.completion'), 'You completed your reading journey.'),
            LearnerStage::GRADE_READY => $this->action('View Diagnostic Result', route('learner.diagnostic.reading-summary'), 'Your diagnostic result shows no module is needed right now.'),
            LearnerStage::COMPLETED => $this->action('View Completion', route('learner.completion'), 'Your learner journey is complete.'),
            default => $this->action('Continue', route('learner.dashboard'), 'Continue your reading journey.'),
        };
    }

    private function action(string $label, string $route, string $message): array
    {
        return compact('label', 'route', 'message');
    }
}
