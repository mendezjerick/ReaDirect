<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\School;
use App\Services\Assessment\FinalAssessmentComparisonService;
use App\Services\AssessmentItemSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_assessment_start_creates_attempt_and_clones_baseline_task_items(): void
    {
        [$learner, $baseline] = $this->learnerWithBaselineDiagnostic();

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('final-assessment.start.store'))
            ->assertRedirect(route('final-assessment.task', 'task-1'));

        $final = AssessmentAttempt::where('attempt_type', 'final_reassessment')->firstOrFail();

        $this->assertSame($baseline->id, $final->baseline_assessment_attempt_id);
        $this->assertSame(10, $final->selectedItems()->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)->count());
        $this->assertSame(
            $baseline->selectedItems()->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)->orderBy('sequence')->first()->source_csv_id,
            $final->selectedItems()->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)->orderBy('sequence')->first()->source_csv_id
        );
    }

    public function test_final_task_one_submission_scores_rule_based_and_routes_to_task_two_b(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();
        $this->withSession(['learner_id' => $learner->id])->post(route('final-assessment.start.store'));
        $final = AssessmentAttempt::where('attempt_type', 'final_reassessment')->firstOrFail();
        $responses = $final->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => $item->prompt_snapshot['prompt'],
                'transcript_source' => 'manual',
            ])
            ->all();

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id])
            ->post(route('final-assessment.task.submit', 'task-1'), ['responses' => $responses])
            ->assertRedirect(route('final-assessment.task', 'task-2b'));

        $final->refresh();

        $this->assertSame(10, (int) $final->task_1_score);
        $this->assertSame(10, (int) $final->task_2a_score);
        $this->assertSame(10, AssessmentTaskResponse::where('assessment_attempt_id', $final->id)->count());
    }

    public function test_final_comparison_service_calculates_deltas_and_percent_change(): void
    {
        $comparison = app(FinalAssessmentComparisonService::class)->computeInitialVsFinal(
            ['crla_total_score' => 15, 'final_reading_score' => 50, 'reading_accuracy' => 70],
            ['crla_total_score' => 24, 'final_reading_score' => 75, 'reading_accuracy' => 80],
        );

        $this->assertSame(9.0, $comparison['deltas']['crla_total_score']);
        $this->assertSame(25.0, $comparison['deltas']['final_reading_score']);
        $this->assertSame(60.0, $comparison['percent_change']['crla_total_score']);
        $this->assertSame('The learner improved in one or more final reassessment areas.', $comparison['summary']);
    }

    private function learnerWithBaselineDiagnostic(): array
    {
        $school = School::create(['name' => 'Final Test School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('FIN-', false),
            'first_name' => 'Final',
            'grade_level' => 'Grade 1',
            'current_stage' => 'final_reassessment_pending',
        ]);
        $module = Module::create([
            'sequence' => 1,
            'key' => 'module_1',
            'title' => 'Letter and Sound Learning',
            'description' => 'Practice',
            'is_active' => true,
        ]);
        $baseline = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'task_1_score' => 5,
            'task_2a_score' => 5,
            'task_2b_score' => 5,
            'crla_total_score' => 15,
            'crla_classification' => 'Moderate Refresher',
            'reading_accuracy' => 70,
            'comprehension_percentage' => 40,
            'final_reading_score' => 52,
            'reading_classification' => 'Developing Reader',
            'assigned_module_id' => $module->id,
            'started_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
        ]);

        foreach (range(1, 10) as $index) {
            $baseline->selectedItems()->create([
                'source_csv_id' => 'BASE-T1-'.$index,
                'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
                'sequence' => $index,
                'prompt_snapshot' => [
                    'prompt' => 'A',
                    'payload' => ['expected_answer' => 'A'],
                    'accepted_answers' => ['A', 'a'],
                ],
                'selected_at' => now()->subDays(10),
            ]);
        }

        return [$learner, $baseline];
    }
}
