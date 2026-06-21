<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleAttempt;
use App\Models\School;
use App\Services\AssessmentItemSelectionService;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LearnerFlowStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_learner_dashboard_shows_start_diagnostic(): void
    {
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Dashboard')
                ->where('flowState.stage', LearnerStage::NEW)
                ->where('flowState.primary_action_label', 'Start Diagnostic')
            );
    }

    public function test_starting_diagnostic_sets_stage_and_does_not_duplicate_active_attempt(): void
    {
        $learner = $this->learner();
        $this->seedTaskOneLetters();

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.diagnostic.start.store'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $firstAttempt = AssessmentAttempt::firstOrFail();

        $this->assertSame(LearnerStage::DIAGNOSTIC_IN_PROGRESS, $learner->refresh()->current_stage);

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.diagnostic.start.store'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $this->assertSame(1, AssessmentAttempt::where('learner_id', $learner->id)->count());
        $this->assertSame($firstAttempt->id, AssessmentAttempt::first()->id);
    }

    public function test_task_one_only_is_still_diagnostic_in_progress_on_dashboard(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);
        AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1_completed',
            'task_1_score' => 5,
            'started_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.stage', LearnerStage::DIAGNOSTIC_IN_PROGRESS)
                ->where('flowState.primary_action_label', 'Continue Diagnostic')
                ->where('latestAttempt', null)
            );
    }

    public function test_active_diagnostic_is_recovered_without_session_and_completed_task_one_cannot_be_revisited(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1_completed',
            'task_1_score' => 5,
            'started_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.diagnostic.task-1'))
            ->assertRedirect(route('learner.diagnostic.task-2a'));

        $this->assertSame($attempt->id, session('assessment_attempt_id'));
    }

    public function test_diagnostic_cannot_skip_ahead_and_task_one_routing_is_enforced(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'started_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-2b'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $attempt->update(['task_1_score' => 8, 'task_2a_score' => 10, 'status' => 'task_1_completed']);
        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-2a'))
            ->assertRedirect(route('learner.diagnostic.task-2b'));
        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-2a-summary'))
            ->assertRedirect(route('learner.diagnostic.task-2b'));

        $attempt->update(['task_1_score' => 4, 'task_2a_score' => null, 'status' => 'task_1_completed']);
        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-2b'))
            ->assertRedirect(route('learner.diagnostic.task-2a'));

        $attempt->update(['task_2a_score' => 6, 'status' => 'task_2a_completed']);
        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-2a-summary'))
            ->assertRedirect(route('learner.diagnostic.crla-summary'));
    }

    public function test_module_access_and_dashboard_actions_follow_current_module_and_stage(): void
    {
        [$moduleOne, $moduleTwo, $moduleThree] = $this->modules();
        $learner = $this->learner([
            'current_module_id' => $moduleTwo->id,
            'current_stage' => LearnerStage::MODULE_ASSIGNED,
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.start', $moduleOne))
            ->assertRedirect(route('learner.dashboard'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.start', $moduleThree))
            ->assertRedirect(route('learner.dashboard'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.stage', LearnerStage::MODULE_ASSIGNED)
                ->where('flowState.current_module_key', 'module_2')
            );
    }

    public function test_module_attempt_is_recovered_without_session_for_practice_and_mastery(): void
    {
        [, $module] = $this->modules();
        $learner = $this->learner([
            'current_module_id' => $module->id,
            'current_stage' => LearnerStage::MODULE_PRACTICE_IN_PROGRESS,
        ]);
        $this->seedModuleActivity($module, 'read_word');
        $attempt = ModuleAttempt::create([
            'learner_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'practice_started',
            'started_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.activity', [$module, 'read_word']))
            ->assertOk();

        $this->assertSame($attempt->id, session('module_attempt_id'));

        $attempt->update(['status' => 'mastery_started']);
        $learner->update(['current_stage' => LearnerStage::MODULE_MASTERY_IN_PROGRESS]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.stage', LearnerStage::MODULE_MASTERY_IN_PROGRESS)
                ->where('flowState.primary_action_label', 'Continue Mastery Check')
            );
    }

    public function test_legacy_extra_drills_stage_final_pending_and_grade_ready_are_actionable(): void
    {
        [$module] = $this->modules();
        $learner = $this->learner([
            'current_module_id' => $module->id,
            'current_stage' => LearnerStage::EXTRA_PHONEME_DRILLS,
        ]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.primary_action_label', 'Start Module')
            );

        $learner->update(['current_module_id' => null, 'current_stage' => LearnerStage::FINAL_REASSESSMENT_PENDING]);
        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('flowState.primary_action_label', 'Start Final Reassessment')
            );

        $learner->update(['current_stage' => LearnerStage::GRADE_READY]);
        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('module', null)
                ->where('flowState.stage', LearnerStage::GRADE_READY)
            );
    }

    public function test_final_reassessment_start_recovery_and_route_guards(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::FINAL_REASSESSMENT_PENDING]);
        $baseline = $this->completedDiagnostic($learner);

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('final-assessment.start.store'))
            ->assertRedirect(route('final-assessment.task', 'task-1'));

        $final = AssessmentAttempt::where('attempt_type', 'final_reassessment')->firstOrFail();
        $this->assertSame($baseline->id, $final->baseline_assessment_attempt_id);
        $this->assertSame(LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS, $learner->refresh()->current_stage);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('final-assessment.task', 'task-2b'))
            ->assertRedirect(route('final-assessment.task', 'task-1'));

        $this->assertSame($final->id, session('final_assessment_attempt_id'));
    }

    public function test_invalid_session_attempt_from_another_learner_is_rejected(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);
        $other = $this->learner();
        $otherAttempt = AssessmentAttempt::create([
            'learner_id' => $other->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'started_at' => now(),
        ]);

        $this->withSession(['learner_id' => $learner->id, 'assessment_attempt_id' => $otherAttempt->id])
            ->get(route('learner.diagnostic.task-1'))
            ->assertRedirect(route('learner.dashboard'));
    }

    private function learner(array $attributes = []): Learner
    {
        $school = School::first() ?? School::create(['name' => 'Flow Test School']);

        return Learner::create(array_merge([
            'school_id' => $school->id,
            'learner_code' => uniqid('FLOW-', false),
            'first_name' => 'Flow',
            'grade_level' => 'Grade 1',
        ], $attributes));
    }

    private function modules(): array
    {
        return [
            Module::firstOrCreate(['key' => 'module_1'], ['sequence' => 1, 'title' => 'Module 1', 'description' => 'Letters', 'is_active' => true]),
            Module::firstOrCreate(['key' => 'module_2'], ['sequence' => 2, 'title' => 'Module 2', 'description' => 'Words', 'is_active' => true]),
            Module::firstOrCreate(['key' => 'module_3'], ['sequence' => 3, 'title' => 'Module 3', 'description' => 'Sentences', 'is_active' => true]),
        ];
    }

    private function seedTaskOneLetters(): void
    {
        foreach (['A', 'C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $letter) {
            LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter '.$letter,
                'prompt' => $letter,
                'payload' => ['source_csv_id' => 'LETTER-'.$letter, 'expected_answer' => $letter],
                'accepted_answers' => [$letter, strtolower($letter)],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }
    }

    private function seedModuleActivity(Module $module, string $activityType): void
    {
        LearningContent::create([
            'content_type' => 'module_activity_selection_rule',
            'title' => 'Rule',
            'payload' => ['module_key' => $module->key, 'activity_type' => $activityType, 'practice_item_count' => 1],
            'is_active' => true,
        ]);

        $content = LearningContent::create([
            'content_type' => 'module_activity',
            'title' => 'Read cat',
            'prompt' => 'Read cat.',
            'payload' => ['source_csv_id' => 'MOD-1', 'expected_answer' => 'cat', 'points' => 1],
            'accepted_answers' => ['cat'],
            'is_active' => true,
        ]);

        ModuleActivity::create([
            'module_id' => $module->id,
            'learning_content_id' => $content->id,
            'sequence' => 1,
            'activity_type' => $activityType,
            'title' => 'Read cat',
            'configuration' => $content->payload,
        ]);
    }

    private function completedDiagnostic(Learner $learner): AssessmentAttempt
    {
        $this->seedTaskOneLetters();

        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'reading_accuracy' => 95,
            'comprehension_percentage' => 100,
            'final_reading_score' => 98,
            'assigned_module_id' => null,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        foreach (range(1, 10) as $index) {
            $attempt->selectedItems()->create([
                'source_csv_id' => 'BASE-'.$index,
                'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
                'sequence' => $index,
                'prompt_snapshot' => ['prompt' => 'A', 'payload' => ['expected_answer' => 'A'], 'accepted_answers' => ['A']],
                'selected_at' => now()->subDay(),
            ]);
        }

        return $attempt;
    }
}
