<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleActivityResponse;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_teacher_dashboard(): void
    {
        $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_teacher_dashboard(): void
    {
        $student = $this->userWithRole('student');

        $this->actingAs($student)->get(route('teacher.dashboard'))->assertForbidden();
    }

    public function test_teacher_dashboard_loads_with_scoped_counts(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $outsideLearner = $this->outsideLearner();
        $this->diagnosticFor($learner, ['status' => 'module_placement_completed']);
        $this->diagnosticFor($outsideLearner, ['status' => 'module_placement_completed']);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/Dashboard')
                ->where('dashboard.counts.total_learners', 1)
                ->where('dashboard.counts.diagnostic_completed', 1)
            );
    }

    public function test_learner_list_only_shows_assigned_learners_and_search_does_not_leak(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $outside = $this->outsideLearner();

        $this->actingAs($teacher)
            ->get(route('teacher.learners.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/LearnerList')
                ->has('learners', 1)
                ->where('learners.0.learner_code', $learner->learner_code)
            );

        $this->actingAs($teacher)
            ->get(route('teacher.learners.index', ['search' => $outside->learner_code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('learners', 0));
    }

    public function test_teacher_can_create_learner_for_assigned_class_only(): void
    {
        [$teacher, $existingLearner] = $this->teacherWithLearner();
        $outside = $this->outsideLearner();

        $this->actingAs($teacher)
            ->get(route('teacher.learners.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/LearnerForm')
                ->has('classes', 1)
                ->where('classes.0.id', $existingLearner->class_id)
            );

        $this->actingAs($teacher)
            ->post(route('teacher.learners.store'), [
                'class_id' => $outside->class_id,
                'learner_code' => 'BLOCKED-1',
                'first_name' => 'Blocked',
                'last_name' => 'Learner',
                'grade_level' => 'Grade 1',
            ])
            ->assertSessionHasErrors('class_id');

        $this->actingAs($teacher)
            ->post(route('teacher.learners.store'), [
                'class_id' => $existingLearner->class_id,
                'learner_code' => 'NEW-1',
                'first_name' => 'New',
                'last_name' => 'Learner',
                'grade_level' => 'Grade 1',
            ])
            ->assertRedirect();

        $learner = Learner::where('learner_code', 'NEW-1')->firstOrFail();

        $this->assertSame($existingLearner->school_id, $learner->school_id);
        $this->assertSame($existingLearner->class_id, $learner->class_id);
        $this->assertSame(LearnerStage::NEW, $learner->current_stage);
    }

    public function test_teacher_can_view_assigned_learner_and_cannot_view_outside_learner(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $outside = $this->outsideLearner();

        $this->actingAs($teacher)
            ->get(route('teacher.learners.show', $learner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/LearnerDetail')
                ->where('learner.learner_code', $learner->learner_code)
            );

        $this->actingAs($teacher)
            ->get(route('teacher.learners.show', $outside))
            ->assertForbidden();
    }

    public function test_learner_detail_handles_diagnostic_and_module_progress(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $this->diagnosticFor($learner);
        $this->moduleAttemptFor($learner);

        $this->actingAs($teacher)
            ->get(route('teacher.learners.show', $learner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('diagnosticSummary.crla_total_score', 27)
                ->where('readingSummary.classification_note', 'Reading classification is based only on final_reading_score.')
                ->has('moduleProgress', 1)
            );
    }

    public function test_assessment_review_shows_scores_and_reading_summary(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $attempt = $this->diagnosticFor($learner);
        AssessmentTaskResponse::create([
            'assessment_attempt_id' => $attempt->id,
            'learner_id' => $learner->id,
            'task_key' => 'task_1_letter',
            'task_type' => 'task_1_letter',
            'item_number' => 1,
            'prompt' => 'A',
            'expected_answer' => 'A',
            'response_text' => 'A',
            'is_correct' => true,
            'score' => 1,
            'rule_applied' => 'CRLA_TASK_1_SCORING_V1',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.learners.assessments.show', [$learner, $attempt]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/AssessmentReview')
                ->where('scoringSummary.crla_total_score', 27)
                ->where('scoringSummary.reading_classification', 'Reading at Grade Level')
                ->has('task1Responses', 1)
            );
    }

    public function test_module_progress_handles_attempts_and_empty_state(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();

        $this->actingAs($teacher)
            ->get(route('teacher.learners.modules.index', $learner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('moduleAttempts', 0));

        $this->moduleAttemptFor($learner);

        $this->actingAs($teacher)
            ->get(route('teacher.learners.modules.index', $learner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('moduleAttempts', 1)
                ->where('moduleAttempts.0.mastery_decision', 'move_to_module_2')
            );
    }

    public function test_csv_exports_are_downloadable_and_teacher_scoped(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $outside = $this->outsideLearner();
        $this->diagnosticFor($learner);

        $this->actingAs($teacher)
            ->get(route('teacher.reports.learner.diagnostic', $learner))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($teacher)
            ->get(route('teacher.reports.learner.diagnostic', $outside))
            ->assertForbidden();
    }

    public function test_class_analytics_loads_for_teacher(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        $this->diagnosticFor($learner);

        $this->actingAs($teacher)
            ->get(route('teacher.analytics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Teacher/ClassAnalytics')
                ->where('analytics.averageCrlaTotalScore', 27)
            );
    }

    private function teacherWithLearner(): array
    {
        $teacher = $this->userWithRole('teacher');
        $school = School::create(['name' => 'Teacher Test School']);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'name' => 'Grade 1 Blue',
            'grade_level' => 'Grade 1',
        ]);
        $learner = Learner::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'learner_code' => uniqid('RD-', false),
            'first_name' => 'Mika',
            'last_name' => 'Reader',
            'grade_level' => 'Grade 1',
        ]);

        return [$teacher, $learner];
    }

    private function outsideLearner(): Learner
    {
        $teacher = $this->userWithRole('teacher');
        $school = School::create(['name' => 'Outside School']);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'name' => 'Grade 1 Red',
            'grade_level' => 'Grade 1',
        ]);

        return Learner::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'learner_code' => uniqid('OUT-', false),
            'first_name' => 'Outside',
            'last_name' => 'Learner',
            'grade_level' => 'Grade 1',
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => uniqid($role, false).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function diagnosticFor(Learner $learner, array $overrides = []): AssessmentAttempt
    {
        return AssessmentAttempt::create($overrides + [
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'task_1_score' => 9,
            'task_2a_score' => 10,
            'task_2b_score' => 8,
            'crla_total_score' => 27,
            'crla_classification' => 'Grade Ready',
            'incorrect_words' => 2,
            'reading_accuracy' => 96,
            'comprehension_correct_count' => 5,
            'comprehension_percentage' => 100,
            'final_reading_score' => 98.4,
            'reading_classification' => 'Reading at Grade Level',
            'rule_applied' => 'MODULE_PLACEMENT_V1',
            'decision_reason' => 'No module needed.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function moduleAttemptFor(Learner $learner): ModuleAttempt
    {
        $module = Module::create([
            'sequence' => 1,
            'key' => 'module_1',
            'title' => 'Letter and Sound Learning',
            'description' => 'Letters',
            'is_active' => true,
        ]);
        $activity = ModuleActivity::create([
            'module_id' => $module->id,
            'sequence' => 1,
            'activity_type' => 'mastery_check',
            'title' => 'Say A',
        ]);
        $attempt = ModuleAttempt::create([
            'learner_id' => $learner->id,
            'module_id' => $module->id,
            'status' => 'completed',
            'score' => 90,
            'mastery_decision' => 'move_to_module_2',
            'rule_applied' => 'MODULE_1_MASTERY_V1',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $item = ModuleAttemptItem::create([
            'module_attempt_id' => $attempt->id,
            'module_activity_id' => $activity->id,
            'activity_type' => 'mastery_check',
            'sequence' => 1,
            'prompt_snapshot' => ['prompt' => 'Say A', 'points' => 1],
            'is_mastery_item' => true,
            'selected_at' => now(),
            'answered_at' => now(),
        ]);
        ModuleActivityResponse::create([
            'module_attempt_id' => $attempt->id,
            'module_activity_id' => $activity->id,
            'module_attempt_item_id' => $item->id,
            'response_text' => 'A',
            'learner_answer' => 'A',
            'expected_answer' => 'A',
            'is_correct' => true,
            'score' => 1,
            'feedback_text' => 'Nice reading!',
            'retry_count' => 0,
            'is_mastery_item' => true,
        ]);

        return $attempt;
    }
}
