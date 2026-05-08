<?php

namespace Tests\Feature;

use App\Models\AgentProfile;
use App\Models\AssessmentAttempt;
use App\Models\AuditLog;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\LlmPromptTemplate;
use App\Models\MasteryThreshold;
use App\Models\Module;
use App\Models\ModuleActivity;
use App\Models\ModuleAttempt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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

    public function test_admin_dashboard_reports_wav2vec2_only_ai_status(): void
    {
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.health' => '/health',
            'readirect_ai.endpoints.version' => '/version',
        ]);
        Http::fake([
            'http://ai.test/health' => Http::response([
                'ok' => true,
                'asr_architecture' => 'wav2vec2_only',
                'active_asr_model' => 'wav2vec2',
                'model_version' => 'letters-v2',
                'base_model' => 'models/wav2vec2-readirect-asr',
                'whisper_removed' => true,
                'wav2vec2_asr_model_name' => 'models/wav2vec2-readirect-asr-letters-v2',
            ]),
            'http://ai.test/version' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($this->userWithRole('system_admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiService.connected', true)
                ->where('aiService.label', 'AI Service Connected')
                ->where('aiService.asr_architecture', 'wav2vec2_only')
                ->where('aiService.whisper_removed', true)
                ->where('aiService.model_size', 'models/wav2vec2-readirect-asr-letters-v2')
                ->where('aiService.model_version', 'letters-v2')
                ->where('aiService.base_model', 'models/wav2vec2-readirect-asr')
            );
    }

    public function test_admin_can_toggle_developer_reinforcement_mode(): void
    {
        $admin = $this->userWithRole('system_admin');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('developerReinforcementMode.visible', true)
                ->where('developerReinforcementMode.enabled', false)
            );

        $this->actingAs($admin)
            ->post(route('admin.developer-reinforcement-mode.update'), ['enabled' => true])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('developerReinforcementMode.visible', true)
                ->where('developerReinforcementMode.enabled', true)
            );
    }

    public function test_non_admin_cannot_toggle_developer_reinforcement_mode(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)
            ->post(route('admin.developer-reinforcement-mode.update'), ['enabled' => true])
            ->assertForbidden();
    }

    public function test_developer_reset_visibility_is_admin_only(): void
    {
        config(['readirect_ai.debug.enable_developer_assessment_reset' => true]);

        $this->get(route('learner.diagnostic.start'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('developerRetest.enabled', false)
            );

        $this->actingAs($this->userWithRole('system_admin'))
            ->get(route('learner.diagnostic.start'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('developerRetest.enabled', true)
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

    public function test_admin_module_content_filters_use_real_module_activity_fields(): void
    {
        $admin = $this->userWithRole('system_admin');
        [$module1, $module2, $module3] = $this->moduleContentFilterData();

        $this->actingAs($admin)
            ->get(route('admin.module-content.index', ['module' => 'module_2']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ModuleContent/Index')
                ->has('activities.data', 2)
                ->where('activities.data.0.module.key', 'module_2')
                ->where('activities.data.1.module.key', 'module_2'));

        $this->actingAs($admin)
            ->get(route('admin.module-content.index', ['activity_type' => 'read_word']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.activity_type', 'read_word')
                ->where('activities.data.0.module.key', 'module_2'));

        $this->actingAs($admin)
            ->get(route('admin.module-content.index', ['module' => 'module_2', 'search' => 'cat']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.title', 'Read cat'));

        $this->actingAs($admin)
            ->get(route('admin.module-content.index', ['is_mastery_item' => 'mastery']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.activity_type', 'mastery_check'));

        $this->actingAs($admin)
            ->get(route('admin.module-content.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.module.key', $module3->key));
    }

    public function test_admin_assessment_content_filters_use_canonical_content_type_keys(): void
    {
        $admin = $this->userWithRole('system_admin');
        LearningContent::create(['content_type' => 'letter', 'title' => 'Letter A', 'prompt' => 'A', 'difficulty' => 'easy', 'is_active' => true]);
        LearningContent::create(['content_type' => 'rhyme_prompt', 'title' => 'Cat rhyme', 'prompt' => 'cat', 'difficulty' => 'easy', 'is_active' => true]);
        LearningContent::create(['content_type' => 'word_sentence', 'title' => 'Find cat', 'prompt' => 'I see a cat.', 'difficulty' => 'medium', 'is_active' => false]);
        LearningContent::create(['content_type' => 'reading_passage', 'title' => 'Passage', 'prompt' => 'Read this.', 'difficulty' => 'medium', 'is_active' => true]);
        LearningContent::create(['content_type' => 'comprehension_question', 'title' => 'Question', 'prompt' => 'Who?', 'difficulty' => 'hard', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.assessment-content.index', ['content_type' => 'task2b_word_sentence']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.content_type', 'word_sentence'));

        $this->actingAs($admin)
            ->get(route('admin.assessment-content.index', ['content_type' => 'task1_letter']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Letter A'));

        $this->actingAs($admin)
            ->get(route('admin.assessment-content.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.title', 'Find cat'));
    }

    public function test_admin_people_and_school_filters_are_applied(): void
    {
        $admin = $this->userWithRole('system_admin');
        $schoolA = School::create(['name' => 'North School', 'district' => 'North', 'division' => 'A', 'is_active' => true]);
        $schoolB = School::create(['name' => 'South School', 'district' => 'South', 'division' => 'B', 'is_active' => false]);
        $classA = SchoolClass::create(['school_id' => $schoolA->id, 'name' => 'Blue', 'grade_level' => 'Grade 1']);
        $classB = SchoolClass::create(['school_id' => $schoolB->id, 'name' => 'Green', 'grade_level' => 'Grade 1']);
        $module = Module::create(['sequence' => 1, 'key' => 'module_1', 'title' => 'Letter and Sound Learning', 'is_active' => true]);
        Learner::create(['school_id' => $schoolA->id, 'class_id' => $classA->id, 'current_module_id' => $module->id, 'learner_code' => 'N-1', 'first_name' => 'Nina', 'grade_level' => 'Grade 1', 'is_active' => true]);
        Learner::create(['school_id' => $schoolB->id, 'class_id' => $classB->id, 'learner_code' => 'S-1', 'first_name' => 'Sam', 'grade_level' => 'Grade 1', 'is_active' => false]);
        $teacherA = $this->userWithRole('teacher');
        $teacherB = $this->userWithRole('teacher');
        $teacherB->update(['is_active' => false]);
        $classA->update(['teacher_id' => $teacherA->id]);
        $classB->update(['teacher_id' => $teacherB->id]);

        $this->actingAs($admin)
            ->get(route('admin.schools.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page->has('schools.data', 1)->where('schools.data.0.name', 'South School'));

        $this->actingAs($admin)
            ->get(route('admin.teachers.index', ['school_id' => $schoolA->id]))
            ->assertInertia(fn (Assert $page) => $page->has('teachers.data', 1)->where('teachers.data.0.id', $teacherA->id));

        $this->actingAs($admin)
            ->get(route('admin.learners.index', ['school_id' => $schoolA->id, 'current_module' => 'module_1']))
            ->assertInertia(fn (Assert $page) => $page->has('learners.data', 1)->where('learners.data.0.learner_code', 'N-1'));
    }

    public function test_admin_can_create_learner_from_form_payload(): void
    {
        $admin = $this->userWithRole('system_admin');
        $school = School::create(['name' => 'Create Learner School']);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Grade 1 Create', 'grade_level' => 'Grade 1']);

        $this->actingAs($admin)
            ->post(route('admin.learners.store'), [
                'school_id' => $school->id,
                'class_id' => $class->id,
                'current_module_id' => '',
                'learner_code' => '',
                'first_name' => 'Created',
                'last_name' => 'Learner',
                'grade_level' => 'Grade 1',
                'current_stage' => '',
            ])
            ->assertRedirect();

        $learner = Learner::where('first_name', 'Created')->firstOrFail();

        $this->assertSame($school->id, $learner->school_id);
        $this->assertSame($class->id, $learner->class_id);
        $this->assertSame(LearnerStage::NEW, $learner->current_stage);
        $this->assertStringStartsWith('LRN-', $learner->learner_code);
    }

    public function test_admin_agent_prompt_audit_and_testing_filters_are_applied(): void
    {
        $admin = $this->userWithRole('system_admin');
        $assessmentAgent = AgentProfile::create(['key' => 'assessment', 'name' => 'Assessment Agent', 'agent_type' => 'assessment', 'purpose' => 'Assess', 'is_active' => true]);
        $coachAgent = AgentProfile::create(['key' => 'coach', 'name' => 'Coach Agent', 'agent_type' => 'coach_feedback', 'purpose' => 'Coach', 'is_active' => false]);
        LlmPromptTemplate::create(['agent_profile_id' => $assessmentAgent->id, 'key' => 'assessment_prompt', 'version' => 1, 'status' => 'active', 'template' => 'Assess']);
        LlmPromptTemplate::create(['agent_profile_id' => $coachAgent->id, 'key' => 'coach_feedback', 'version' => 1, 'status' => 'draft', 'template' => 'Coach']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'admin.testing.flow_jump', 'auditable_type' => Learner::class, 'auditable_id' => 100, 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'admin.prompt.updated', 'auditable_type' => LlmPromptTemplate::class, 'auditable_id' => 200, 'ip_address' => '127.0.0.1']);
        $learner = $this->learner();
        $module = Module::create(['sequence' => 1, 'key' => 'module_1', 'title' => 'Module 1', 'is_active' => true]);
        AssessmentAttempt::create(['learner_id' => $learner->id, 'attempt_type' => 'diagnostic', 'status' => 'task_1', 'is_sandbox' => true]);
        ModuleAttempt::create(['learner_id' => $learner->id, 'module_id' => $module->id, 'status' => 'completed', 'is_sandbox' => false]);

        $this->actingAs($admin)
            ->get(route('admin.agents.index', ['agent_type' => 'coach_feedback']))
            ->assertInertia(fn (Assert $page) => $page->has('agents', 1)->where('agents.0.key', 'coach'));

        $this->actingAs($admin)
            ->get(route('admin.prompts.index', ['agent_type' => 'assessment', 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->has('prompts.data', 1)->where('prompts.data.0.key', 'assessment_prompt'));

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'admin.prompt.updated']))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.action', 'admin.prompt.updated'));

        $this->actingAs($admin)
            ->get(route('admin.testing.index', ['attempt_type' => 'module', 'sandbox' => 'live']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sandboxAssessments', 0)
                ->has('sandboxModules', 1)
                ->where('sandboxModules.0.status', 'completed'));
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

    private function moduleContentFilterData(): array
    {
        $modules = [];
        foreach ([1 => ['module_1', 'Letter and Sound Learning'], 2 => ['module_2', 'Word Reading'], 3 => ['module_3', 'Sentence Reading and Fluency']] as $sequence => [$key, $title]) {
            $modules[$key] = Module::create(['sequence' => $sequence, 'key' => $key, 'title' => $title, 'is_active' => true]);
        }

        $this->createModuleActivity($modules['module_1'], 'hear_and_repeat', 'Say A', true, false);
        $this->createModuleActivity($modules['module_2'], 'read_word', 'Read cat', true, false);
        $this->createModuleActivity($modules['module_2'], 'mastery_check', 'Mastery word', true, true);
        $this->createModuleActivity($modules['module_3'], 'read_sentence', 'Read sentence', false, false);

        return [$modules['module_1'], $modules['module_2'], $modules['module_3']];
    }

    private function createModuleActivity(Module $module, string $activityType, string $title, bool $active, bool $mastery): ModuleActivity
    {
        $content = LearningContent::create([
            'content_type' => 'module_activity',
            'title' => $title,
            'prompt' => $title,
            'difficulty' => 'grade_1',
            'is_active' => $active,
        ]);

        return ModuleActivity::create([
            'module_id' => $module->id,
            'learning_content_id' => $content->id,
            'sequence' => $mastery ? 100 : 1,
            'activity_type' => $activityType,
            'title' => $title,
            'configuration' => ['is_active' => $active, 'is_mastery_item' => $mastery],
        ]);
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
