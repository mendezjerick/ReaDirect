<?php

namespace App\Services\Admin;

use App\Models\AssessmentAttempt;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\Recommendation;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\CrlaScoringService;
use App\Services\DiagnosticPlacementService;
use App\Services\ModuleMasteryService;
use App\Services\ReadingComprehensionScoringService;
use App\Support\LearnerStage;
use Illuminate\Support\Facades\DB;

class ModuleMasterySimulatorService
{
    public const LEARNER_CODE = 'MM-SIMULATOR';

    public function __construct(
        private readonly CrlaScoringService $crla,
        private readonly ReadingComprehensionScoringService $reading,
        private readonly DiagnosticPlacementService $placement,
        private readonly ModuleMasteryService $mastery,
    ) {}

    public function learner(): Learner
    {
        $school = School::firstOrCreate(
            ['name' => 'ReaDirect Simulator School'],
            ['district' => 'Admin QA', 'division' => 'Simulator', 'metadata' => ['admin_simulator' => true], 'is_active' => false]
        );

        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Admin Simulator'],
            ['grade_level' => 'Grade 1', 'school_year' => 'Simulator']
        );

        $learner = Learner::firstOrNew(['learner_code' => self::LEARNER_CODE]);
        $metadata = $learner->metadata ?? [];
        $learner->fill([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'current_module_id' => $learner->current_module_id,
            'current_stage' => LearnerStage::normalize($learner->current_stage),
            'first_name' => 'MM',
            'last_name' => 'Simulator',
            'grade_level' => 'Grade 1',
            'metadata' => array_merge($metadata, [
                'admin_module_mastery_simulator' => true,
                'normal_learner_access_disabled' => true,
            ]),
            'is_active' => false,
        ]);

        if (! $learner->exists || ! $learner->current_stage) {
            $learner->current_stage = LearnerStage::DIAGNOSTIC_IN_PROGRESS;
            $learner->current_module_id = null;
        }

        $learner->save();

        return $learner->fresh(['currentModule']);
    }

    public function reset(): Learner
    {
        return DB::transaction(function (): Learner {
            $learner = $this->learner();

            Recommendation::where('learner_id', $learner->id)->delete();
            AudioFile::where('learner_id', $learner->id)->delete();
            AssessmentAttempt::where('learner_id', $learner->id)->delete();
            ModuleAttempt::where('learner_id', $learner->id)->delete();

            $learner->update([
                'current_module_id' => null,
                'current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS,
            ]);

            return $learner->fresh(['currentModule']);
        });
    }

    public function simulate(array $scores): array
    {
        return DB::transaction(function () use ($scores): array {
            $learner = $this->reset();

            $taskOneScore = (int) $scores['task_1_score'];
            $enteredTaskTwoScore = (int) $scores['task_2_score'];
            $taskThreeScore = (int) $scores['task_3_score'];
            $incorrectWords = (int) $scores['incorrect_words'];
            $comprehensionCorrect = (int) $scores['comprehension_correct_count'];

            $taskOneRoute = $this->crla->routeTaskOne($taskOneScore);
            $effectiveTaskTwoScore = $taskOneRoute['requires_task_2a'] ? $enteredTaskTwoScore : 10;
            $effectiveTaskThreeScore = $taskOneRoute['requires_task_2a'] ? 0 : $taskThreeScore;
            $crlaTotal = $this->crla->calculateTotalScore($taskOneScore, $effectiveTaskTwoScore, $effectiveTaskThreeScore);
            $crlaBreakdown = $this->crla->classifyTotalScoreWithRule($crlaTotal);
            $passageEligible = $this->crla->shouldAdministerPassage($taskOneScore, $crlaTotal);

            $accuracy = $passageEligible ? $this->reading->calculateAccuracyPercentage($incorrectWords) : 0.0;
            $comprehensionPercentage = $passageEligible
                ? $this->reading->calculateComprehensionPercentage($comprehensionCorrect, ReadingComprehensionScoringService::ASSESSMENT_COMPREHENSION_QUESTION_COUNT)
                : 0.0;
            $comprehensionContribution = round($comprehensionPercentage * 0.60, 2);
            $accuracyContribution = round($accuracy * 0.40, 2);
            $finalReadingScore = $passageEligible ? $this->reading->calculateFinalReadingScore($comprehensionPercentage, $accuracy) : 0.0;
            $readingBreakdown = $this->reading->classifyReadingLevelWithRule($finalReadingScore);

            $attempt = AssessmentAttempt::create([
                'learner_id' => $learner->id,
                'attempt_type' => 'diagnostic',
                'status' => 'reading_completed',
                'task_1_score' => $taskOneScore,
                'task_2a_score' => $effectiveTaskTwoScore,
                'task_2b_score' => $effectiveTaskThreeScore,
                'crla_total_score' => $crlaTotal,
                'crla_classification' => $crlaBreakdown['classification'],
                'reading_accuracy' => $accuracy,
                'incorrect_words' => $passageEligible ? $incorrectWords : 0,
                'comprehension_correct_count' => $passageEligible ? $comprehensionCorrect : 0,
                'comprehension_percentage' => $comprehensionPercentage,
                'final_reading_score' => $finalReadingScore,
                'reading_classification' => $readingBreakdown['classification'],
                'is_sandbox' => true,
                'started_at' => now(),
            ]);

            $placement = $this->placement->completePlacement($attempt);
            $attempt = $placement['attempt'];
            $module = $placement['module'];

            $result = [
                'learner' => $this->learnerPayload($attempt->learner),
                'attempt' => [
                    'id' => $attempt->public_id,
                    'is_sandbox' => true,
                    'status' => $attempt->status,
                    'completed_at' => $attempt->completed_at?->toISOString(),
                ],
                'inputs' => [
                    'task_1_score' => $taskOneScore,
                    'task_2_score_entered' => $enteredTaskTwoScore,
                    'task_3_score' => $taskThreeScore,
                    'incorrect_words' => $incorrectWords,
                    'comprehension_correct_count' => $comprehensionCorrect,
                    'comprehension_total' => ReadingComprehensionScoringService::ASSESSMENT_COMPREHENSION_QUESTION_COUNT,
                ],
                'task_routing' => [
                    ...$taskOneRoute,
                    'condition' => $taskOneRoute['requires_task_2a']
                        ? 'Task 1 score is 0-6, so Task 2A is administered and Task 2B plus passage reading are automatically recorded as 0.'
                        : 'Task 1 score is 7-10, so Task 2 is auto-scored as 10 in the real diagnostic flow.',
                ],
                'computed' => [
                    'effective_task_2_score' => $effectiveTaskTwoScore,
                    'effective_task_3_score' => $effectiveTaskThreeScore,
                    'crla_total_score' => $crlaTotal,
                    'crla_percentage' => round(($crlaTotal / 30) * 100, 2),
                    'crla_classification' => $crlaBreakdown['classification'],
                    'passage_eligible' => $passageEligible,
                    'reading_accuracy' => $accuracy,
                    'comprehension_percentage' => $comprehensionPercentage,
                    'reading_weighting' => '60% comprehension + 40% passage accuracy',
                    'weight_calculation' => [
                        'comprehension_percentage' => $comprehensionPercentage,
                        'comprehension_weight' => 0.60,
                        'comprehension_contribution' => $comprehensionContribution,
                        'accuracy_percentage' => $accuracy,
                        'accuracy_weight' => 0.40,
                        'accuracy_contribution' => $accuracyContribution,
                        'formula' => "({$comprehensionPercentage} x 0.60) + ({$accuracy} x 0.40)",
                        'sum' => round($comprehensionContribution + $accuracyContribution, 2),
                    ],
                    'final_reading_score' => $finalReadingScore,
                    'reading_classification' => $readingBreakdown['classification'],
                    'combined_score_used_for_placement' => 'CRLA classification plus reading classification',
                ],
                'rule_tables' => $this->ruleTables(),
                'rules' => [
                    'crla_routing' => $taskOneRoute['rule_applied'],
                    'crla_classification' => $crlaBreakdown,
                    'reading_classification' => $readingBreakdown,
                    'module_placement' => [
                        'rule_applied' => $placement['decision']['rule_applied'],
                        'matched_condition' => $placement['decision']['matched_condition'],
                        'decision' => $placement['decision']['decision'],
                        'decision_reason' => $placement['decision']['decision_reason'],
                        'placement_explanation' => $placement['decision']['placement_explanation'],
                    ],
                ],
                'module' => $module ? $module->only('key', 'title', 'description') : null,
                'placement_decision' => $placement['decision'],
            ];

            $attempt->update(['comparison_summary' => ['module_mastery_simulator' => $result]]);

            return $result;
        });
    }

    public function latestResult(): ?array
    {
        $learner = $this->learner();
        $attempt = AssessmentAttempt::where('learner_id', $learner->id)
            ->where('is_sandbox', true)
            ->where('attempt_type', 'diagnostic')
            ->where('status', 'module_placement_completed')
            ->latest()
            ->first();

        return $attempt?->comparison_summary['module_mastery_simulator'] ?? null;
    }

    public function learnerPayload(?Learner $learner = null): array
    {
        $learner ??= $this->learner();

        return [
            'id' => $learner->public_id,
            'name' => trim($learner->first_name.' '.$learner->last_name),
            'learner_code' => $learner->learner_code,
            'current_stage' => $learner->current_stage,
            'current_module' => $learner->currentModule?->only('key', 'title'),
            'is_simulator' => (bool) ($learner->metadata['admin_module_mastery_simulator'] ?? false),
        ];
    }

    private function ruleTables(): array
    {
        return [
            [
                'title' => 'Task 1 Routing',
                'columns' => ['Task 1 score', 'Task 2 handling', 'Next task', 'Rule'],
                'rows' => [
                    ['0-6', 'Use entered Task 2A score', 'Task 2A then diagnostic ends', 'CRLA_TASK_1_ROUTING_V1'],
                    ['7-10', 'Auto-score Task 2A as 10', 'Task 2B', 'CRLA_TASK_1_ROUTING_V1'],
                ],
            ],
            [
                'title' => 'CRLA Classification',
                'columns' => ['CRLA total', 'Classification', 'Placement effect'],
                'rows' => [
                    ['0-10', CrlaScoringService::FULL_REFRESHER, 'Assign Module 1'],
                    ['11-16', CrlaScoringService::MODERATE_REFRESHER, 'Assign Module 1'],
                    ['17-26', CrlaScoringService::LIGHT_REFRESHER, 'Assign Module 1'],
                    ['27-30', CrlaScoringService::GRADE_READY, 'Use reading classification for placement'],
                ],
            ],
            [
                'title' => 'Reading Classification',
                'columns' => ['Final reading score', 'Classification', 'Placement effect when CRLA is Grade Ready'],
                'rows' => [
                    ['0-25', ReadingComprehensionScoringService::LOW_EMERGING, 'Assign Module 2'],
                    ['26-50', ReadingComprehensionScoringService::HIGH_EMERGING, 'Assign Module 2'],
                    ['51-75', ReadingComprehensionScoringService::DEVELOPING, 'Assign Module 3'],
                    ['76-90', ReadingComprehensionScoringService::TRANSITIONING, 'Assign Module 3'],
                    ['91-100', ReadingComprehensionScoringService::GRADE_LEVEL, 'No module needed'],
                ],
            ],
            [
                'title' => 'Diagnostic Module Placement',
                'columns' => ['CRLA classification', 'Reading classification', 'Result', 'Rule'],
                'rows' => [
                    ['Full, Moderate, or Light Refresher', 'Any reading classification', 'Module 1', 'MODULE_PLACEMENT_V1'],
                    ['Grade Ready', 'Low Emerging or High Emerging', 'Module 2', 'MODULE_PLACEMENT_V1'],
                    ['Grade Ready', 'Developing or Transitioning', 'Module 3', 'MODULE_PLACEMENT_V1'],
                    ['Grade Ready', 'Reading at Grade Level', 'No module needed', 'MODULE_PLACEMENT_V1'],
                ],
            ],
            [
                'title' => 'Module Mastery Progression',
                'columns' => ['Module', 'Mastery score', 'Decision', 'Next', 'Rule'],
                'rows' => array_map(
                    fn (array $row): array => [
                        $row['module'],
                        $row['score_range'],
                        $row['decision'],
                        $row['next'],
                        $row['rule_applied'],
                    ],
                    $this->mastery->ruleTable()
                ),
            ],
        ];
    }
}
