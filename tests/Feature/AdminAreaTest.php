<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\LearningContent;
use App\Models\Learner;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleAttempt;
use App\Models\MasteryThreshold;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_access_admin_dashboard(): void
    {
        $admin = $this->userWithRole('system_admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('dashboard.counts')
            );
    }

    public function test_non_admins_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $teacher = $this->userWithRole('teacher');
        $student = $this->userWithRole('student');

        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_create_edit_and_deactivate_assessment_content(): void
    {
        $admin = $this->userWithRole('system_admin');

        $response = $this->actingAs($admin)->post(route('admin.assessment-content.store'), [
            'content_type' => 'task_1_letter',
            'title' => 'Letter A',
            'prompt' => 'A',
            'difficulty' => 'grade_1',
            'accepted_answers' => 'a|A',
            'payload' => '{"expected_answer":"A"}',
        ]);

        $item = LearningContent::where('title', 'Letter A')->firstOrFail();
        $response->assertRedirect(route('admin.assessment-content.show', $item));
        $this->assertSame(['a', 'A'], $item->accepted_answers);

        $this->actingAs($admin)->put(route('admin.assessment-content.update', $item), [
            'content_type' => 'task_1_letter',
            'title' => 'Letter A updated',
            'prompt' => 'A',
            'difficulty' => 'grade_1',
            'accepted_answers' => 'a',
            'payload' => '{"expected_answer":"A"}',
            'is_active' => true,
        ])->assertRedirect(route('admin.assessment-content.show', $item));

        $this->actingAs($admin)->post(route('admin.assessment-content.deactivate', $item))->assertRedirect();
        $this->assertFalse($item->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.assessment_content.deactivated']);
    }

    public function test_admin_testing_can_create_sandbox_diagnostic_and_view_debug(): void
    {
        $admin = $this->userWithRole('system_admin');
        $learner = $this->learner();
        foreach (range('A', 'J') as $letter) {
            LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter '.$letter,
                'prompt' => $letter,
                'accepted_answers' => [strtolower($letter)],
                'payload' => ['expected_answer' => $letter],
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admin.testing.start-sandbox'), [
                'learner_id' => $learner->id,
                'type' => 'diagnostic',
            ])
            ->assertRedirect(route('admin.testing.flow-jump'));

        $attempt = AssessmentAttempt::where('learner_id', $learner->id)->where('is_sandbox', true)->firstOrFail();
        $this->assertTrue($attempt->is_sandbox);

        $this->actingAs($admin)
            ->get(route('admin.testing.assessment.debug', $attempt))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testing/AssessmentDebug')
                ->where('debug.attempt.is_sandbox', true)
                ->has('debug.items')
            );
    }

    public function test_student_cannot_use_query_param_to_access_testing_debug(): void
    {
        $student = $this->userWithRole('student');
        $attempt = AssessmentAttempt::create([
            'learner_id' => $this->learner()->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'is_sandbox' => true,
        ]);

        $this->actingAs($student)
            ->get(route('admin.testing.assessment.debug', $attempt).'?admin_testing=1')
            ->assertForbidden();
    }

    public function test_admin_jump_targets_prepare_sessions_and_include_modules(): void
    {
        $admin = $this->userWithRole('system_admin');
        $learner = $this->learner();
        $this->seedDiagnosticTaskOneItems();
        [$module, $activityType] = $this->moduleWithContent('module_1');

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('admin.testing.flow-jump'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testing/FlowJump')
                ->where('targets.0.group', 'Learner')
                ->where('targets.18.group', 'Modules')
            );

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('admin.testing.jump', 'diagnostic-task-1'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('admin.testing.jump', "module-{$module->key}-activity"))
            ->assertRedirect(route('learner.modules.activity', [$module, $activityType]));
    }

    public function test_sandbox_attempts_are_excluded_from_teacher_reports(): void
    {
        [$teacher, $learner] = $this->teacherWithLearner();
        AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'crla_total_score' => 5,
            'is_sandbox' => true,
        ]);
        AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'crla_total_score' => 27,
            'crla_classification' => 'Grade Ready',
            'is_sandbox' => false,
        ]);

        $response = $this->actingAs($teacher)->get(route('teacher.reports.learner.diagnostic', $learner));
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('27', $csv);
        $this->assertStringNotContainsString(',5,', $csv);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => uniqid($role).'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function learner(): Learner
    {
        $school = School::create(['name' => 'Admin Test School']);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Grade 1 Blue', 'grade_level' => 'Grade 1']);

        return Learner::create([
            'school_id' => $school->id,
            'class_id' => $class->id,
            'learner_code' => uniqid('ADM-', false),
            'first_name' => 'Admin',
            'last_name' => 'Learner',
            'grade_level' => 'Grade 1',
        ]);
    }

    private function seedDiagnosticTaskOneItems(): void
    {
        foreach (range('A', 'J') as $letter) {
            LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter '.$letter,
                'prompt' => $letter,
                'accepted_answers' => [strtolower($letter)],
                'payload' => ['expected_answer' => $letter],
                'is_active' => true,
            ]);
        }
    }

    private function moduleWithContent(string $key): array
    {
        $module = Module::create([
            'sequence' => 1,
            'key' => $key,
            'title' => 'Module 1',
            'description' => 'Test module',
            'is_active' => true,
        ]);

        $activityType = 'hear_and_repeat';
        foreach (range(1, 5) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => 'Say A '.$index,
                'prompt' => 'A',
                'accepted_answers' => ['a'],
                'payload' => ['expected_answer' => 'A', 'points' => 1],
                'is_active' => true,
            ]);
            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => $index,
                'activity_type' => $activityType,
                'title' => 'Say A '.$index,
                'configuration' => ['is_mastery_item' => false, 'is_active' => true],
            ]);
        }
        foreach (range(1, 10) as $index) {
            $content = LearningContent::create([
                'content_type' => 'module_activity',
                'title' => 'Mastery A '.$index,
                'prompt' => 'A',
                'accepted_answers' => ['a'],
                'payload' => ['expected_answer' => 'A', 'points' => 1],
                'is_active' => true,
            ]);
            ModuleActivity::create([
                'module_id' => $module->id,
                'learning_content_id' => $content->id,
                'sequence' => 100 + $index,
                'activity_type' => 'mastery_check',
                'title' => 'Mastery A '.$index,
                'configuration' => ['is_mastery_item' => true, 'is_active' => true],
            ]);
        }
        LearningContent::create([
            'content_type' => 'module_activity_selection_rule',
            'title' => $key.' hear rule',
            'prompt' => null,
            'payload' => ['module_key' => $key, 'activity_type' => $activityType, 'practice_item_count' => 5, 'mastery_item_count' => 0],
            'is_active' => true,
        ]);
        LearningContent::create([
            'content_type' => 'module_activity_selection_rule',
            'title' => $key.' mastery rule',
            'prompt' => null,
            'payload' => ['module_key' => $key, 'activity_type' => 'mastery_check', 'practice_item_count' => 0, 'mastery_item_count' => 10],
            'is_active' => true,
        ]);
        MasteryThreshold::create([
            'module_id' => $module->id,
            'min_score' => 0,
            'max_score' => 100,
            'decision' => 'repeat_module_1',
            'rule_key' => 'TEST_MASTERY',
        ]);

        return [$module, $activityType];
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
            'learner_code' => uniqid('TCH-', false),
            'first_name' => 'Teacher',
            'last_name' => 'Learner',
            'grade_level' => 'Grade 1',
        ]);

        return [$teacher, $learner];
    }
}
