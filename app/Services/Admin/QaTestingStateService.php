<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\AudioFile;
use App\Models\LearnerModuleUsedTarget;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Models\Recommendation;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\AssessmentItemSelectionService;
use App\Services\CrlaScoringService;
use App\Services\DiagnosticPlacementService;
use App\Services\ModuleActivitySelectionService;
use App\Services\ModuleMasteryService;
use App\Services\ReadingComprehensionScoringService;
use App\Support\LearnerStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QaTestingStateService
{
    public const LEARNER_CODE = 'QA-TESTER';
    private const DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY = 'diagnostic_tutorial_completed_attempt_id';

    public function __construct(
        private readonly AssessmentItemSelectionService $assessmentItems,
        private readonly ModuleActivitySelectionService $moduleItems,
        private readonly CrlaScoringService $crla,
        private readonly ReadingComprehensionScoringService $reading,
        private readonly DiagnosticPlacementService $placement,
        private readonly ModuleMasteryService $mastery,
    ) {}

    public function tester(): Learner
    {
        $school = School::firstOrCreate(
            ['name' => 'ReaDirect QA Testing School'],
            ['district' => 'Admin QA', 'division' => 'Testing', 'metadata' => ['admin_qa_testing' => true], 'is_active' => false]
        );

        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'QA Testing'],
            ['grade_level' => 'Grade 1', 'school_year' => 'QA']
        );

        $tester = Learner::firstOrNew(['learner_code' => self::LEARNER_CODE]);
        $tester->fill([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'first_name' => 'Tester',
            'last_name' => 'QA',
            'grade_level' => 'Grade 1',
            'metadata' => array_merge($tester->metadata ?? [], [
                'admin_qa_tester' => true,
                'normal_learner_access_disabled' => true,
            ]),
            'is_active' => false,
        ]);

        if (! $tester->exists || ! $tester->current_stage) {
            $tester->current_stage = LearnerStage::NEW;
            $tester->current_module_id = null;
        }

        $tester->save();

        return $tester->fresh(['currentModule']);
    }

    public function activate(Request $request): Learner
    {
        $tester = $this->tester();
        $request->session()->put('admin_testing_mode', true);
        $request->session()->put('admin_testing_learner_id', $tester->id);
        $request->session()->put('learner_id', $tester->id);

        return $tester;
    }

    public function reset(Request $request): Learner
    {
        return DB::transaction(function () use ($request): Learner {
            $tester = $this->resetTesterData();

            $this->clearAttemptSession($request);
            $this->activate($request);

            return $tester->fresh(['currentModule']);
        });
    }

    public function exit(Request $request): void
    {
        DB::transaction(function () use ($request): void {
            $this->resetTesterData();
            $this->clearTestingSession($request);
        });
    }

    public function prepareJump(Request $request, string $target): array
    {
        return DB::transaction(function () use ($request, $target): array {
            $tester = $this->reset($request);

            if (str_starts_with($target, 'diagnostic-')) {
                return $this->prepareDiagnosticJump($request, $tester, $target);
            }

            if (str_starts_with($target, 'module-')) {
                return $this->prepareModuleJump($request, $tester, $target);
            }

            if (str_starts_with($target, 'final-')) {
                return $this->prepareFinalJump($request, $tester, $target);
            }

            return $this->prepareLearnerJump($request, $tester, $target);
        });
    }

    private function prepareLearnerJump(Request $request, Learner $tester, string $target): array
    {
        return match ($target) {
            'learner-dashboard' => $this->redirect($tester, route('learner.dashboard')),
            'learner-progress' => $this->preparePlacedLearnerPage($tester, route('learner.progress')),
            'learner-rewards' => $this->preparePlacedLearnerPage($tester, route('learner.rewards')),
            'learner-help' => $this->preparePlacedLearnerPage($tester, route('learner.help')),
            'learner-modules' => $this->preparePlacedLearnerPage($tester, route('learner.modules.index')),
            'learner-completion' => $this->prepareCompletionJump($request, $tester),
            default => abort(404),
        };
    }

    private function preparePlacedLearnerPage(Learner $tester, string $url): array
    {
        if (Module::where('key', 'module_1')->exists()) {
            $this->seedDiagnosticForModule($tester, 'module_1');
        }

        return $this->redirect($tester->fresh(['currentModule']), $url);
    }

    private function prepareCompletionJump(Request $request, Learner $tester): array
    {
        $baseline = $this->seedReadyForFinalAssessment($tester);

        $attempt = AssessmentAttempt::create([
            'learner_id' => $tester->id,
            'baseline_assessment_attempt_id' => $baseline->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);

        $request->session()->put('final_assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);
        $this->seedFinalComplete($attempt, 2, 4);

        $tester->update([
            'current_module_id' => null,
            'current_stage' => LearnerStage::FINAL_REASSESSMENT_COMPLETED,
        ]);

        return $this->redirect($tester->fresh(['currentModule']), route('learner.completion'));
    }

    private function prepareDiagnosticJump(Request $request, Learner $tester, string $target): array
    {
        if ($target === 'diagnostic-start') {
            $request->session()->forget(self::DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY);

            return $this->redirect($tester, route('learner.diagnostic.start'));
        }

        $attempt = $this->diagnosticAttempt($tester);
        $this->putAssessmentSession($request, $attempt);

        return match ($target) {
            'diagnostic-tutorial' => tap(
                $this->redirect($tester, route('learner.diagnostic.tutorial')),
                function () use ($request, $attempt): void {
                    $request->session()->forget(self::DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY);
                    $this->assessmentItems->selectTask1LettersForAttempt($attempt);
                }
            ),
            'diagnostic-task-1' => tap(
                $this->redirect($tester, route('learner.diagnostic.task-1')),
                function () use ($request, $attempt): void {
                    $request->session()->put(self::DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY, $attempt->id);
                    $this->assessmentItems->selectTask1LettersForAttempt($attempt);
                }
            ),
            'diagnostic-task-routing' => tap(
                $this->redirect($tester, route('learner.diagnostic.task-routing')),
                function () use ($request, $attempt): void {
                    $route = $this->crla->routeTaskOne(5);
                    $attempt->update([
                        'task_1_score' => 5,
                        'task_2a_score' => $route['assigned_task_2a_score'],
                        'status' => 'task_1_completed',
                        'rule_applied' => $route['rule_applied'],
                        'decision_reason' => 'Task 1 score is 0-6, so Task 2A is required.',
                    ]);
                    $request->session()->put('task_one_route', $route);
                }
            ),
            'diagnostic-task-2a' => tap(
                $this->redirect($tester, route('learner.diagnostic.task-2a')),
                function () use ($attempt): void {
                    $attempt->update(['task_1_score' => 5, 'task_2a_score' => null, 'status' => 'task_1_completed']);
                    $this->assessmentItems->selectTask2ARhymingPromptsForAttempt($attempt);
                }
            ),
            'diagnostic-task-2a-summary' => tap(
                $this->redirect($tester, route('learner.diagnostic.task-2a-summary')),
                fn () => $this->seedLowTaskTwoASummary($attempt)
            ),
            'diagnostic-task-2b' => tap(
                $this->redirect($tester, route('learner.diagnostic.task-2b')),
                function () use ($attempt): void {
                    $attempt->update(['task_1_score' => 8, 'task_2a_score' => 10, 'status' => 'task_1_completed']);
                    $this->assessmentItems->selectTask2BWordSentenceItemsForAttempt($attempt);
                }
            ),
            'diagnostic-crla-summary' => tap(
                $this->redirect($tester, route('learner.diagnostic.crla-summary')),
                fn () => $this->seedCrlaComplete($attempt, 8, 10, 8)
            ),
            'diagnostic-reading-intro' => tap(
                $this->redirect($tester, route('learner.diagnostic.reading-intro')),
                fn () => $this->seedCrlaComplete($attempt, 8, 10, 8)
            ),
            'diagnostic-story-selection' => tap(
                $this->redirect($tester, route('learner.diagnostic.story-selection')),
                fn () => $this->seedCrlaComplete($attempt, 8, 10, 8)
            ),
            'diagnostic-passage' => tap(
                $this->redirect($tester, route('learner.diagnostic.passage')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 8, 10, 8);
                    $this->assessmentItems->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-001');
                }
            ),
            'diagnostic-comprehension' => tap(
                $this->redirect($tester, route('learner.diagnostic.comprehension')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 8, 10, 8);
                    $this->assessmentItems->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-001');
                    $this->seedPassageComplete($attempt, 2);
                }
            ),
            'diagnostic-reading-summary' => tap(
                $this->redirect($tester, route('learner.diagnostic.reading-summary')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 8, 10, 8);
                    $this->assessmentItems->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-001');
                    $this->seedReadingComplete($attempt, 2, 4);
                }
            ),
            'diagnostic-module-placement' => tap(
                $this->redirect($tester, route('learner.diagnostic.module-placement')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 10, 10, 10);
                    $this->seedReadingComplete($attempt, 10, 1);
                }
            ),
            default => abort(404),
        };
    }

    private function prepareModuleJump(Request $request, Learner $tester, string $target): array
    {
        preg_match('/^module-(module_[123]|advanced_module)-(overview|activity(?:-.+)?|mastery|result|extra)$/', $target, $matches);
        abort_unless($matches, 404);

        $module = Module::where('key', $matches[1])->firstOrFail();
        $pageToken = $matches[2];
        $requestedActivityType = str_starts_with($pageToken, 'activity-')
            ? substr($pageToken, strlen('activity-'))
            : null;
        $page = str_starts_with($pageToken, 'activity') ? 'activity' : $pageToken;
        if ($module->key === 'advanced_module') {
            $this->seedReadyForAdvancedModule($tester);
        } else {
            $this->seedDiagnosticForModule($tester, $module->key);
        }

        $attempt = ModuleAttempt::create([
            'learner_id' => $tester->id,
            'module_id' => $module->id,
            'status' => 'practice_started',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);

        $request->session()->put('module_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_module_attempt_id', $attempt->id);
        $tester->update([
            'current_module_id' => $module->id,
            'current_stage' => LearnerStage::MODULE_PRACTICE_IN_PROGRESS,
        ]);

        $activityType = $requestedActivityType ?: $this->firstPracticeActivity($module);
        abort_if($page === 'activity' && ! in_array($activityType, $this->moduleItems->practiceActivityTypes($module), true), 404);

        return match ($page) {
            'overview' => $this->redirect($tester->fresh(), route('learner.modules.overview', $module)),
            'activity' => tap(
                $this->redirect($tester->fresh(), route('learner.modules.activity', [$module, $activityType])),
                function () use ($attempt, $module, $activityType): void {
                    $this->completePracticePrerequisitesBefore($attempt, $module, $activityType);
                    $this->moduleItems->selectPracticeItemsForAttempt($attempt, $activityType, $this->moduleItems->practiceCountFor($module, $activityType));
                }
            ),
            'mastery' => tap(
                $this->redirect($tester->fresh(), route('learner.modules.mastery-check', $module)),
                fn () => $this->completePracticePrerequisites($attempt, $module)
            ),
            'result' => tap(
                $this->redirect($tester->fresh(), route('learner.modules.mastery-result', $module)),
                fn () => $this->seedCompletedModuleAttempt($attempt, $module, $this->resultScoreFor($module))
            ),
            default => abort(404),
        };
    }

    private function prepareFinalJump(Request $request, Learner $tester, string $target): array
    {
        $baseline = $this->seedReadyForFinalAssessment($tester);

        if ($target === 'final-start') {
            return $this->redirect($tester->fresh(), route('final-assessment.start'));
        }

        $attempt = AssessmentAttempt::create([
            'learner_id' => $tester->id,
            'baseline_assessment_attempt_id' => $baseline->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);
        $request->session()->put('final_assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);
        $tester->update(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);

        return match ($target) {
            'final-task-1' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'task-1')),
                fn () => $this->assessmentItems->selectTask1LettersForAttempt($attempt)
            ),
            'final-task-2a' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'task-2a')),
                function () use ($attempt): void {
                    $attempt->update(['task_1_score' => 5, 'task_2a_score' => null, 'status' => 'task_1_completed']);
                    $this->assessmentItems->selectTask2ARhymingPromptsForAttempt($attempt);
                }
            ),
            'final-task-2b' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'task-2b')),
                function () use ($attempt): void {
                    $attempt->update(['task_1_score' => 8, 'task_2a_score' => 10, 'status' => 'task_1_completed']);
                    $this->assessmentItems->selectTask2BWordSentenceItemsForAttempt($attempt);
                }
            ),
            'final-story-selection' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'story-selection')),
                fn () => $this->seedCrlaComplete($attempt, 8, 10, 8)
            ),
            'final-passage' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'passage')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 8, 10, 8);
                    $this->assessmentItems->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-001');
                }
            ),
            'final-comprehension' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.task', 'comprehension')),
                function () use ($attempt): void {
                    $this->seedCrlaComplete($attempt, 8, 10, 8);
                    $this->assessmentItems->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-001');
                    $this->seedPassageComplete($attempt, 2);
                }
            ),
            'final-summary' => tap(
                $this->redirect($tester->fresh(), route('final-assessment.summary')),
                fn () => $this->seedFinalComplete($attempt, 2, 4)
            ),
            default => abort(404),
        };
    }

    private function diagnosticAttempt(Learner $tester): AssessmentAttempt
    {
        $tester->update(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);

        return AssessmentAttempt::create([
            'learner_id' => $tester->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);
    }

    private function seedDiagnosticForModule(Learner $tester, string $moduleKey): AssessmentAttempt
    {
        [$taskOne, $taskTwoA, $taskTwoB, $incorrectWords, $correctAnswers] = match ($moduleKey) {
            'module_1' => [4, 4, 2, 0, 4],
            'module_2' => [10, 10, 10, 50, 0],
            'module_3' => [10, 10, 10, 10, 3],
            default => throw new \InvalidArgumentException('Unknown module key.'),
        };

        $attempt = $this->diagnosticAttempt($tester);
        $this->seedCrlaComplete($attempt, $taskOne, $taskTwoA, $taskTwoB);
        $this->seedReadingComplete($attempt, $incorrectWords, $correctAnswers);
        $this->placement->completePlacement($attempt);

        return $attempt->fresh();
    }

    private function seedReadyForFinalAssessment(Learner $tester): AssessmentAttempt
    {
        $baseline = $this->seedDiagnosticForModule($tester, 'module_3');
        $baseline = $baseline->fresh();

        foreach (['module_1', 'module_2', 'module_3'] as $moduleKey) {
            $module = Module::where('key', $moduleKey)->firstOrFail();
            $attempt = ModuleAttempt::create([
                'learner_id' => $tester->id,
                'module_id' => $module->id,
                'status' => 'practice_started',
                'is_sandbox' => true,
                'started_at' => now(),
            ]);
            $this->seedCompletedModuleAttempt($attempt, $module, 90);
        }

        $tester->update([
            'current_module_id' => null,
            'current_stage' => LearnerStage::FINAL_REASSESSMENT_PENDING,
        ]);

        return $baseline;
    }

    private function seedReadyForAdvancedModule(Learner $tester): AssessmentAttempt
    {
        $baseline = $this->seedReadyForFinalAssessment($tester);

        $attempt = AssessmentAttempt::create([
            'learner_id' => $tester->id,
            'baseline_assessment_attempt_id' => $baseline->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'is_sandbox' => true,
            'started_at' => now(),
        ]);

        $this->seedPerfectFinalComplete($attempt);

        $tester->update([
            'current_module_id' => null,
            'current_stage' => LearnerStage::FINAL_REASSESSMENT_COMPLETED,
        ]);

        return $attempt->fresh();
    }

    private function seedCrlaComplete(AssessmentAttempt $attempt, int $taskOne, int $taskTwoA, int $taskTwoB): void
    {
        $total = $this->crla->calculateTotalScore($taskOne, $taskTwoA, $taskTwoB);
        $attempt->update([
            'task_1_score' => $taskOne,
            'task_2a_score' => $taskTwoA,
            'task_2b_score' => $taskTwoB,
            'crla_total_score' => $total,
            'crla_classification' => $this->crla->classifyTotalScore($total),
            'status' => 'crla_completed',
        ]);
    }

    private function seedLowTaskTwoASummary(AssessmentAttempt $attempt): void
    {
        $attempt->update(array_merge([
            'task_1_score' => 5,
            'task_2a_score' => 6,
            'status' => 'crla_completed',
        ], $this->crla->completeWithoutTask2BOrPassage(5, 6)));
    }

    private function seedPassageComplete(AssessmentAttempt $attempt, int $incorrectWords): void
    {
        $attempt->update([
            'incorrect_words' => $incorrectWords,
            'reading_accuracy' => $this->reading->calculateAccuracyPercentage($incorrectWords),
            'status' => 'passage_completed',
        ]);
    }

    private function seedReadingComplete(AssessmentAttempt $attempt, int $incorrectWords, int $correctAnswers): void
    {
        $correctAnswers = min(ReadingComprehensionScoringService::ASSESSMENT_COMPREHENSION_QUESTION_COUNT, max(0, $correctAnswers));
        $accuracy = $this->reading->calculateAccuracyPercentage($incorrectWords);
        $comprehension = $this->reading->calculateComprehensionPercentage($correctAnswers, ReadingComprehensionScoringService::ASSESSMENT_COMPREHENSION_QUESTION_COUNT);
        $final = $this->reading->calculateFinalReadingScore($comprehension, $accuracy);

        $attempt->update([
            'incorrect_words' => $incorrectWords,
            'reading_accuracy' => $accuracy,
            'comprehension_correct_count' => $correctAnswers,
            'comprehension_percentage' => $comprehension,
            'final_reading_score' => $final,
            'reading_classification' => $this->reading->classifyReadingLevelFromFinalScore($final),
            'status' => 'reading_completed',
        ]);
    }

    private function seedFinalComplete(AssessmentAttempt $attempt, int $incorrectWords, int $correctAnswers): void
    {
        $this->seedCrlaComplete($attempt, 8, 10, 8);
        $this->seedReadingComplete($attempt, $incorrectWords, $correctAnswers);
        $attempt->update([
            'status' => 'final_reassessment_completed',
            'completed_at' => now(),
        ]);
    }

    private function seedPerfectFinalComplete(AssessmentAttempt $attempt): void
    {
        $this->seedCrlaComplete($attempt, 10, 10, 10);
        $this->seedReadingComplete($attempt, 0, ReadingComprehensionScoringService::ASSESSMENT_COMPREHENSION_QUESTION_COUNT);
        $attempt->update([
            'status' => 'final_reassessment_completed',
            'completed_at' => now(),
        ]);
    }

    private function completePracticePrerequisites(ModuleAttempt $attempt, Module $module): void
    {
        foreach ($this->moduleItems->practiceActivityTypes($module) as $activityType) {
            $items = $this->moduleItems->selectPracticeItemsForAttempt(
                $attempt,
                $activityType,
                $this->moduleItems->practiceCountFor($module, $activityType)
            );
            $items->each(fn (ModuleAttemptItem $item) => $this->seedCorrectModuleResponse($attempt, $item));
            $this->moduleItems->completePracticeLessonAttempt($attempt, $activityType, $items);
        }

        $attempt->update(['status' => 'practice_started']);
    }

    private function completePracticePrerequisitesBefore(ModuleAttempt $attempt, Module $module, string $targetActivityType): void
    {
        foreach ($this->moduleItems->practiceActivityTypes($module) as $activityType) {
            if ($activityType === $targetActivityType) {
                break;
            }

            $items = $this->moduleItems->selectPracticeItemsForAttempt(
                $attempt,
                $activityType,
                $this->moduleItems->practiceCountFor($module, $activityType)
            );
            $items->each(fn (ModuleAttemptItem $item) => $this->seedCorrectModuleResponse($attempt, $item));
            $this->moduleItems->completePracticeLessonAttempt($attempt, $activityType, $items);
        }

        $attempt->update(['status' => 'practice_started']);
    }

    private function seedCorrectModuleResponse(ModuleAttempt $attempt, ModuleAttemptItem $item): void
    {
        $snapshot = $item->prompt_snapshot ?? [];
        $payload = $snapshot['payload'] ?? [];
        $expected = (string) (
            $snapshot['accepted_answers'][0]
            ?? $payload['expected_answer']
            ?? $payload['expected_text']
            ?? $payload['target_sentence']
            ?? $payload['target_word']
            ?? $snapshot['prompt']
            ?? ''
        );

        ModuleActivityResponse::updateOrCreate(
            [
                'module_attempt_id' => $attempt->id,
                'module_attempt_item_id' => $item->id,
            ],
            [
                'module_activity_id' => $item->module_activity_id,
                'response_text' => $expected,
                'learner_answer' => $expected,
                'expected_answer' => $expected,
                'is_correct' => true,
                'score' => 1,
                'feedback_text' => 'Correct.',
                'retry_count' => 0,
                'is_mastery_item' => false,
                'metadata' => ['admin_qa_seeded' => true],
            ],
        );

        $item->update(['answered_at' => now()]);
    }

    private function seedCompletedModuleAttempt(ModuleAttempt $attempt, Module $module, float|int $score): void
    {
        $this->completePracticePrerequisites($attempt, $module);
        $decision = $this->mastery->decide($module->key, $score);
        $nextModule = $decision['next_module_key'] ? Module::where('key', $decision['next_module_key'])->first() : null;

        $attempt->update([
            'status' => 'completed',
            'score' => $score,
            'mastery_decision' => $decision['decision_key'],
            'rule_applied' => $decision['rule_applied'],
            'decision_reason' => $decision['user_friendly_message'],
            'completed_at' => now(),
        ]);

        if ($module->key === 'advanced_module') {
            if ($decision['decision_key'] === 'advanced_module_complete') {
                app(\App\Services\CielFocusModeService::class)
                    ->awardAdvancedModuleStar($attempt->learner_id, $module->id, $attempt->id);
            }

            return;
        }

        $attempt->learner->update([
            'current_module_id' => $nextModule?->id,
            'current_stage' => $decision['decision_key'] === 'proceed_to_reassessment'
                ? LearnerStage::FINAL_REASSESSMENT_PENDING
                : LearnerStage::MODULE_ASSIGNED,
        ]);
    }

    private function firstPracticeActivity(Module $module): string
    {
        return $this->moduleItems->practiceActivityTypes($module)[0] ?? 'mastery_check';
    }

    private function resultScoreFor(Module $module): int
    {
        return $module->key === 'module_3' ? 90 : 80;
    }

    private function putAssessmentSession(Request $request, AssessmentAttempt $attempt): void
    {
        $request->session()->put('assessment_attempt_id', $attempt->id);
        $request->session()->put('admin_testing_assessment_attempt_id', $attempt->id);
    }

    private function clearAttemptSession(Request $request): void
    {
        $request->session()->forget([
            'assessment_attempt_id',
            'final_assessment_attempt_id',
            'module_attempt_id',
            'task_one_route',
            'admin_testing_assessment_attempt_id',
            'admin_testing_module_attempt_id',
            self::DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY,
        ]);
    }

    private function clearTestingSession(Request $request): void
    {
        $request->session()->forget([
            'admin_testing_mode',
            'admin_testing_learner_id',
            'admin_testing_assessment_attempt_id',
            'admin_testing_module_attempt_id',
            'learner_id',
            'assessment_attempt_id',
            'final_assessment_attempt_id',
            'module_attempt_id',
            'task_one_route',
            self::DIAGNOSTIC_TUTORIAL_COMPLETED_ATTEMPT_KEY,
        ]);
    }

    private function resetTesterData(): Learner
    {
        $tester = $this->tester();

        AudioFile::where('learner_id', $tester->id)->delete();
        Recommendation::where('learner_id', $tester->id)->delete();
        AssessmentAttempt::where('learner_id', $tester->id)->delete();
        ModuleAttempt::where('learner_id', $tester->id)->delete();
        LearnerModuleUsedTarget::where('learner_id', $tester->id)->delete();

        $tester->update([
            'current_module_id' => null,
            'current_stage' => LearnerStage::NEW,
        ]);

        return $tester;
    }

    private function redirect(Learner $tester, string $url): array
    {
        return [
            'learner' => $tester,
            'redirect' => $url,
        ];
    }
}
