<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use App\Services\ModuleActivitySelectionService;

class AdminTestingService
{
    public function startSandboxDiagnostic(Learner $learner, User $admin): AssessmentAttempt
    {
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);

        app(AssessmentItemSelectionService::class)->selectTask1LettersForAttempt($attempt);

        return $attempt;
    }

    public function sandboxDiagnosticFor(Learner $learner, User $admin): AssessmentAttempt
    {
        return AssessmentAttempt::where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->where('is_sandbox', true)
            ->latest()
            ->first()
            ?? $this->startSandboxDiagnostic($learner, $admin);
    }

    public function startSandboxFinal(Learner $learner, User $admin): AssessmentAttempt
    {
        $baseline = AssessmentAttempt::where('learner_id', $learner->id)
            ->where('attempt_type', 'diagnostic')
            ->where('is_sandbox', false)
            ->latest()
            ->first();

        return AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'baseline_assessment_attempt_id' => $baseline?->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);
    }

    public function sandboxFinalFor(Learner $learner, User $admin): AssessmentAttempt
    {
        return AssessmentAttempt::where('learner_id', $learner->id)
            ->where('attempt_type', 'final_reassessment')
            ->where('is_sandbox', true)
            ->latest()
            ->first()
            ?? $this->startSandboxFinal($learner, $admin);
    }

    public function startSandboxModule(Learner $learner, Module $module): ModuleAttempt
    {
        $attempt = ModuleAttempt::create([
            'learner_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'practice_started',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);

        $selection = app(ModuleActivitySelectionService::class);
        $firstActivity = $selection->practiceActivityTypes($module)[0] ?? 'read_word';
        $selection->selectPracticeItemsForAttempt($attempt, $firstActivity, $selection->practiceCountFor($module, $firstActivity));

        return $attempt;
    }

    public function sandboxModuleFor(Learner $learner, Module $module): ModuleAttempt
    {
        return ModuleAttempt::where('learner_id', $learner->id)
            ->where('module_id', $module->id)
            ->where('is_sandbox', true)
            ->latest()
            ->first()
            ?? $this->startSandboxModule($learner, $module);
    }

    public function jumpTargets(Learner $learner): array
    {
        $targets = [
            ['group' => 'Learner', 'label' => 'Learner dashboard', 'target' => 'learner-dashboard'],
            ['group' => 'Diagnostic', 'label' => 'Diagnostic start', 'target' => 'diagnostic-start'],
            ['group' => 'Diagnostic', 'label' => 'Task 1', 'target' => 'diagnostic-task-1'],
            ['group' => 'Diagnostic', 'label' => 'Task 2A', 'target' => 'diagnostic-task-2a'],
            ['group' => 'Diagnostic', 'label' => 'Task 2B', 'target' => 'diagnostic-task-2b'],
            ['group' => 'Diagnostic', 'label' => 'CRLA summary', 'target' => 'diagnostic-crla-summary'],
            ['group' => 'Diagnostic', 'label' => 'Reading intro', 'target' => 'diagnostic-reading-intro'],
            ['group' => 'Diagnostic', 'label' => 'Passage reading', 'target' => 'diagnostic-passage'],
            ['group' => 'Diagnostic', 'label' => 'Comprehension questions', 'target' => 'diagnostic-comprehension'],
            ['group' => 'Diagnostic', 'label' => 'Reading summary', 'target' => 'diagnostic-reading-summary'],
            ['group' => 'Diagnostic', 'label' => 'Module placement result', 'target' => 'diagnostic-module-placement'],
            ['group' => 'Final reassessment', 'label' => 'Start', 'target' => 'final-start'],
            ['group' => 'Final reassessment', 'label' => 'Task 1', 'target' => 'final-task-1'],
            ['group' => 'Final reassessment', 'label' => 'Task 2A', 'target' => 'final-task-2a'],
            ['group' => 'Final reassessment', 'label' => 'Task 2B', 'target' => 'final-task-2b'],
            ['group' => 'Final reassessment', 'label' => 'Passage', 'target' => 'final-passage'],
            ['group' => 'Final reassessment', 'label' => 'Comprehension', 'target' => 'final-comprehension'],
            ['group' => 'Final reassessment', 'label' => 'Summary', 'target' => 'final-summary'],
        ];

        foreach (Module::orderBy('sequence')->get() as $module) {
            $activityType = app(ModuleActivitySelectionService::class)->practiceActivityTypes($module)[0] ?? 'mastery_check';
            $moduleTargets = [
                ['label' => "{$module->title} overview", 'target' => "module-{$module->key}-overview"],
                ['label' => "{$module->title} activity", 'target' => "module-{$module->key}-activity", 'activity_type' => $activityType],
                ['label' => "{$module->title} mastery check", 'target' => "module-{$module->key}-mastery"],
                ['label' => "{$module->title} mastery result", 'target' => "module-{$module->key}-result"],
                ['label' => "{$module->title} extra drills", 'target' => "module-{$module->key}-extra"],
            ];

            foreach ($moduleTargets as $target) {
                $targets[] = ['group' => 'Modules', 'module_key' => $module->key, ...$target];
            }
        }

        return collect($targets)
            ->map(fn (array $target) => $target + ['url' => route('admin.testing.jump', $target['target'])])
            ->all();
    }
}
