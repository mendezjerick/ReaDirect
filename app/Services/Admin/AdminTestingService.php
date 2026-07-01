<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleAttempt;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use App\Services\ModuleExperienceService;
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
        $firstActivity = $selection->practiceActivityTypes($module)[0] ?? 'display_word_reading';
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
            ['category' => 'dashboards', 'group' => 'Dashboards', 'label' => 'Learner dashboard', 'target' => 'learner-dashboard'],
            ['category' => 'dashboards', 'group' => 'Dashboards', 'label' => 'Progress dashboard', 'target' => 'learner-progress'],
            ['category' => 'dashboards', 'group' => 'Dashboards', 'label' => 'Rewards dashboard', 'target' => 'learner-rewards'],
            ['category' => 'dashboards', 'group' => 'Dashboards', 'label' => 'Help dashboard', 'target' => 'learner-help'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Diagnostic start', 'target' => 'diagnostic-start'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Diagnostic tutorial', 'target' => 'diagnostic-tutorial'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Task 1', 'target' => 'diagnostic-task-1'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Task 2A', 'target' => 'diagnostic-task-2a'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Task 2B', 'target' => 'diagnostic-task-2b'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Reading intro', 'target' => 'diagnostic-reading-intro'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Story selection', 'target' => 'diagnostic-story-selection'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Passage reading', 'target' => 'diagnostic-passage'],
            ['category' => 'diagnostic', 'group' => 'Diagnostic', 'label' => 'Comprehension questions', 'target' => 'diagnostic-comprehension'],
            ['category' => 'modules', 'group' => 'Modules', 'label' => 'Module list', 'target' => 'learner-modules'],
        ];

        $selection = app(ModuleActivitySelectionService::class);
        $experience = app(ModuleExperienceService::class);
        foreach (Module::orderBy('sequence')->get() as $module) {
            $activityTypes = $selection->practiceActivityTypes($module);
            $lessonBoxes = collect($experience->overview($module, $activityTypes)['lesson_boxes'] ?? [])->keyBy('key');

            $targets[] = [
                'category' => 'modules',
                'group' => 'Modules',
                'module_key' => $module->key,
                'label' => "{$module->title} overview",
                'target' => "module-{$module->key}-overview",
            ];

            foreach (array_values(array_filter($activityTypes, fn (string $type): bool => $type !== 'mastery_check')) as $index => $activityType) {
                $lessonTitle = $lessonBoxes->get($activityType)['title'] ?? str($activityType)->replace('_', ' ')->title()->toString();
                $targets[] = [
                    'category' => 'modules',
                    'group' => 'Modules',
                    'module_key' => $module->key,
                    'activity_type' => $activityType,
                    'label' => "{$module->title} Lesson ".($index + 1).": {$lessonTitle}",
                    'target' => "module-{$module->key}-activity-{$activityType}",
                ];
            }

            $targets[] = [
                'category' => 'modules',
                'group' => 'Modules',
                'module_key' => $module->key,
                'label' => "{$module->title} mastery check",
                'target' => "module-{$module->key}-mastery",
            ];
        }

        array_push($targets,
            ['category' => 'final', 'group' => 'Final', 'label' => 'Final start', 'target' => 'final-start'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Task 1', 'target' => 'final-task-1'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Task 2A', 'target' => 'final-task-2a'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Task 2B', 'target' => 'final-task-2b'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Story selection', 'target' => 'final-story-selection'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Passage', 'target' => 'final-passage'],
            ['category' => 'final', 'group' => 'Final', 'label' => 'Comprehension', 'target' => 'final-comprehension'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Learner completion', 'target' => 'learner-completion'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Diagnostic task 1 routing result', 'target' => 'diagnostic-task-routing'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Diagnostic task 2A summary', 'target' => 'diagnostic-task-2a-summary'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Diagnostic CRLA summary', 'target' => 'diagnostic-crla-summary'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Diagnostic reading summary', 'target' => 'diagnostic-reading-summary'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Diagnostic module placement result', 'target' => 'diagnostic-module-placement'],
            ['category' => 'results', 'group' => 'Results', 'label' => 'Final summary', 'target' => 'final-summary'],
        );

        foreach (Module::orderBy('sequence')->get() as $module) {
            $targets[] = [
                'category' => 'results',
                'group' => 'Results',
                'module_key' => $module->key,
                'label' => "{$module->title} mastery result",
                'target' => "module-{$module->key}-result",
            ];
        }

        return collect($targets)
            ->map(fn (array $target) => $target + ['url' => route('admin.testing.jump', $target['target'])])
            ->all();
    }
}
