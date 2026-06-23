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
use Database\Seeders\DiagnosticContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                ->where('aiService.agent_decision.status', 'deterministic')
                ->where('aiService.agent_decision.label', 'Deterministic Agent Decisions')
                ->where('aiService.agent_decision.mode', 'deterministic')
            );
    }

    public function test_developer_reset_visibility_is_admin_only(): void
    {
        config(['readirect_ai.debug.enable_developer_assessment_reset' => true]);
        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.diagnostic.start'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('developerRetest.enabled', false)
            );

        $this->actingAs($this->userWithRole('system_admin'))
            ->withSession(['learner_id' => $learner->id])
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
        foreach (['A', 'C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $letter) {
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

        $this->assertDatabaseMissing('assessment_attempts', ['learner_id' => $learner->id]);

        $tester = Learner::where('learner_code', 'QA-TESTER')->firstOrFail();
        $this->assertTrue((bool) ($tester->metadata['admin_qa_tester'] ?? false));

        $attempt = AssessmentAttempt::where('learner_id', $tester->id)->where('is_sandbox', true)->firstOrFail();
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

    public function test_true_sandbox_is_admin_only_and_loads_as_independent_testing_area(): void
    {
        $admin = $this->userWithRole('system_admin');
        $student = $this->userWithRole('student');
        LearningContent::create([
            'content_type' => 'letter',
            'title' => 'Letter C',
            'prompt' => 'C',
            'payload' => ['expected_answer' => 'C'],
            'accepted_answers' => ['C'],
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->get(route('admin.testing.true-sandbox.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.testing.true-sandbox.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testing/TrueSandbox')
                ->where('sections.0.key', 'diagnostic_letters')
                ->where('initialItems.0.expected_text', 'C')
                ->where('routes.items', route('admin.testing.true-sandbox.items'))
                ->where('routes.analyze', route('admin.testing.true-sandbox.analyze'))
            );
    }

    public function test_module_mastery_simulator_is_admin_only_and_loads_mm_learner(): void
    {
        $admin = $this->userWithRole('system_admin');
        $student = $this->userWithRole('student');

        $this->actingAs($student)
            ->get(route('admin.testing.module-mastery-simulator.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.testing.module-mastery-simulator.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testing/ModuleMasterySimulator')
                ->where('learner.name', 'MM Simulator')
                ->where('learner.learner_code', 'MM-SIMULATOR')
                ->where('learner.is_simulator', true)
                ->where('result', null)
                ->where('routes.simulate', route('admin.testing.module-mastery-simulator.store'))
                ->where('routes.reset', route('admin.testing.module-mastery-simulator.reset'))
            );
    }

    public function test_module_mastery_simulator_uses_real_diagnostic_placement_services_and_resets_mm(): void
    {
        $admin = $this->userWithRole('system_admin');
        $modules = $this->placementModules();

        $this->actingAs($admin)
            ->post(route('admin.testing.module-mastery-simulator.store'), [
                'task_1_score' => 10,
                'task_2_score' => 0,
                'task_3_score' => 10,
                'incorrect_words' => 10,
                'comprehension_correct_count' => 1,
            ])
            ->assertRedirect(route('admin.testing.module-mastery-simulator.index'));

        $learner = Learner::where('learner_code', 'MM-SIMULATOR')->firstOrFail();
        $attempt = AssessmentAttempt::where('learner_id', $learner->id)->firstOrFail();

        $this->assertTrue($attempt->is_sandbox);
        $this->assertSame('module_placement_completed', $attempt->status);
        $this->assertSame(10, $attempt->task_2a_score);
        $this->assertSame(30, $attempt->crla_total_score);
        $this->assertSame('Grade Ready', $attempt->crla_classification);
        $this->assertSame(80.0, $attempt->reading_accuracy);
        $this->assertSame(20.0, $attempt->comprehension_percentage);
        $this->assertSame(44.0, $attempt->final_reading_score);
        $this->assertSame('High Emerging Reader', $attempt->reading_classification);
        $this->assertSame($modules['module_2']->id, $attempt->assigned_module_id);
        $this->assertSame($modules['module_2']->id, $learner->fresh()->current_module_id);

        $this->assertDatabaseHas('recommendations', [
            'learner_id' => $learner->id,
            'assessment_attempt_id' => $attempt->id,
            'recommendation_type' => 'module_placement',
            'decision' => 'assign_module_2',
            'rule_applied' => 'MODULE_PLACEMENT_V1',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.testing.module-mastery-simulator.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.module.key', 'module_2')
                ->where('result.inputs.task_2_score_entered', 0)
                ->where('result.computed.effective_task_2_score', 10)
                ->where('result.computed.weight_calculation.comprehension_contribution', 12)
                ->where('result.computed.weight_calculation.accuracy_contribution', 32)
                ->where('result.computed.weight_calculation.sum', 44)
                ->where('result.rules.module_placement.rule_applied', 'MODULE_PLACEMENT_V1')
                ->where('result.rules.module_placement.decision', 'assign_module_2')
                ->where('result.rule_tables.0.title', 'Task 1 Routing')
                ->where('result.rule_tables.4.title', 'Module Mastery Progression')
            );

        $this->actingAs($admin)
            ->post(route('admin.testing.module-mastery-simulator.reset'))
            ->assertRedirect(route('admin.testing.module-mastery-simulator.index'));

        $this->assertDatabaseMissing('assessment_attempts', ['learner_id' => $learner->id]);
        $this->assertDatabaseMissing('recommendations', ['learner_id' => $learner->id]);
        $this->assertNull($learner->fresh()->current_module_id);
        $this->assertSame(LearnerStage::DIAGNOSTIC_IN_PROGRESS, $learner->fresh()->current_stage);
    }

    public function test_mm_simulator_learner_code_cannot_enter_normal_learner_flow(): void
    {
        $admin = $this->userWithRole('system_admin');

        $this->actingAs($admin)
            ->get(route('admin.testing.module-mastery-simulator.index'))
            ->assertOk();

        $this->post(route('learner.access.store'), ['learner_code' => 'MM-SIMULATOR'])
            ->assertSessionHasErrors('learner_code');
    }

    public function test_true_sandbox_can_load_module_asr_items_without_attempt_state(): void
    {
        $admin = $this->userWithRole('system_admin');
        $module = Module::create(['sequence' => 1, 'key' => 'module_1', 'title' => 'Module 1', 'is_active' => true]);
        $content = LearningContent::create([
            'content_type' => 'module_activity',
            'title' => 'Read cat',
            'prompt' => 'cat',
            'payload' => ['expected_answer' => 'cat'],
            'accepted_answers' => ['cat'],
            'is_active' => true,
        ]);
        ModuleActivity::create([
            'module_id' => $module->id,
            'learning_content_id' => $content->id,
            'sequence' => 1,
            'activity_type' => 'read_word',
            'title' => 'Read cat',
            'configuration' => ['is_active' => true],
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.testing.true-sandbox.items', [
                'section' => 'module_activities',
                'module_id' => $module->id,
            ]))
            ->assertOk()
            ->assertJsonPath('items.0.source', 'module_activity')
            ->assertJsonPath('items.0.module.key', 'module_1')
            ->assertJsonPath('items.0.prompt_type', 'word');
    }

    public function test_true_sandbox_analyze_calls_asr_directly_and_returns_debug_contract(): void
    {
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'raw_transcript' => 'hund',
                'corrected_transcript' => 'hand',
                'displayed_transcript' => 'hand',
                'expected_text' => 'hand',
                'prompt_type' => 'word',
                'accepted' => true,
                'retry_required' => false,
                'uncertain' => false,
                'correction_strategy_used' => 'dynamic_asr_spelling_variant',
                'variant_reason' => 'raw transcript appears to be a noisy ASR spelling of the expected word',
                'gop_score' => 0.84,
            ]),
        ]);

        $response = $this->actingAs($this->userWithRole('system_admin'))
            ->post(route('admin.testing.true-sandbox.analyze'), [
                'section' => 'word_pronunciation',
                'item_id' => 'content:123',
                'item_source' => 'learning_content',
                'expected_text' => 'hand',
                'prompt_text' => 'hand',
                'prompt_type' => 'word',
                'task_type' => 'word_pronunciation',
                'activity_type' => 'word_pronunciation',
                'assessment_type' => 'true_sandbox',
                'audio' => UploadedFile::fake()->create('answer.webm', 12, 'audio/webm'),
                'duration_seconds' => 1.2,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('raw_transcript', 'hund')
            ->assertJsonPath('corrected_transcript', 'hand')
            ->assertJsonPath('displayed_transcript', 'hand')
            ->assertJsonPath('scoring.accepted', true)
            ->assertJsonPath('gop_score', 0.84);

        Http::assertSent(fn ($request) => $request->url() === 'http://ai.test/analyze-audio'
            && $request['expected_text'] === 'hand'
            && $request['content_metadata']['true_sandbox'] === true
            && ! array_key_exists('learner_id', $request->data()));
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
                ->where('learner.learner_code', 'QA-TESTER')
                ->where('targets.0.group', 'Learner')
                ->where('targets.18.group', 'Modules')
            );

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('admin.testing.jump', 'diagnostic-task-1'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $tester = Learner::where('learner_code', 'QA-TESTER')->firstOrFail();
        $this->assertDatabaseMissing('assessment_attempts', ['learner_id' => $learner->id]);
        $this->assertDatabaseHas('assessment_attempts', [
            'learner_id' => $tester->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'is_sandbox' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('admin.testing.jump', "module-{$module->key}-activity"))
            ->assertRedirect(route('learner.modules.activity', [$module, $activityType]));

        $tester->refresh();
        $this->assertDatabaseMissing('assessment_attempts', [
            'learner_id' => $tester->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
        ]);
        $this->assertSame($module->id, $tester->current_module_id);
        $this->assertDatabaseHas('module_attempts', [
            'learner_id' => $tester->id,
            'module_id' => $module->id,
            'is_sandbox' => true,
            'status' => 'practice_started',
        ]);
    }

    public function test_qa_testing_prepares_tester_for_final_assessment_without_real_learner_state(): void
    {
        $admin = $this->userWithRole('system_admin');
        $realLearner = $this->learner();
        $this->seedDiagnosticTaskOneItems();
        $this->moduleWithContent('module_1');
        $this->moduleWithContent('module_2');
        $this->moduleWithContent('module_3');

        $this->actingAs($admin)
            ->withSession(['admin_testing_mode' => true, 'admin_testing_learner_id' => $realLearner->id])
            ->get(route('admin.testing.jump', 'final-task-1'))
            ->assertRedirect(route('final-assessment.task', 'task-1'));

        $tester = Learner::where('learner_code', 'QA-TESTER')->firstOrFail();
        $this->assertDatabaseMissing('assessment_attempts', ['learner_id' => $realLearner->id]);
        $this->assertSame(LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS, $tester->current_stage);

        $baseline = AssessmentAttempt::where('learner_id', $tester->id)
            ->where('attempt_type', 'diagnostic')
            ->where('status', 'module_placement_completed')
            ->firstOrFail();
        $this->assertTrue($baseline->is_sandbox);

        $final = AssessmentAttempt::where('learner_id', $tester->id)
            ->where('attempt_type', 'final_reassessment')
            ->firstOrFail();
        $this->assertSame($baseline->id, $final->baseline_assessment_attempt_id);
        $this->assertTrue($final->is_sandbox);

        $this->assertSame(3, ModuleAttempt::where('learner_id', $tester->id)
            ->where('is_sandbox', true)
            ->where('status', 'completed')
            ->count());
        $this->assertDatabaseHas('module_attempts', [
            'learner_id' => $tester->id,
            'mastery_decision' => 'proceed_to_reassessment',
            'rule_applied' => 'MODULE_3_MASTERY_V1',
        ]);
    }

    public function test_all_qa_flow_jump_targets_open_their_destination_pages(): void
    {
        $admin = $this->userWithRole('system_admin');
        $this->seed(DiagnosticContentSeeder::class);
        $this->moduleWithContent('module_1');
        $this->moduleWithContent('module_2');
        $this->moduleWithContent('module_3');

        $flowJump = $this->actingAs($admin)
            ->get(route('admin.testing.flow-jump'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Testing/FlowJump')
                ->has('targets', 30)
            );

        $targets = $flowJump->viewData('page')['props']['targets'];

        foreach ($targets as $target) {
            $jump = $this->actingAs($admin)
                ->get(route('admin.testing.jump', $target['target']))
                ->assertRedirect();

            $destination = $jump->headers->get('Location');
            $opened = $this->actingAs($admin)->get($destination);

            if ($target['target'] === 'final-summary') {
                $opened->assertRedirect(route('learner.completion'));
                $this->actingAs($admin)->get(route('learner.completion'))->assertOk();

                continue;
            }

            $opened->assertOk();
        }
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
        $assessmentAgent = AgentProfile::create(['key' => 'assessment', 'name' => 'Miss Vivian', 'agent_type' => 'assessment', 'purpose' => 'Assess', 'is_active' => true]);
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
        foreach (['A', 'C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $letter) {
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
        $sequence = (int) str_replace('module_', '', $key);
        $module = Module::create([
            'sequence' => $sequence,
            'key' => $key,
            'title' => 'Module '.$sequence,
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

    private function placementModules(): array
    {
        $modules = [];

        foreach ([1 => ['module_1', 'Letter and Sound Learning'], 2 => ['module_2', 'Word Reading'], 3 => ['module_3', 'Sentence Reading and Fluency']] as $sequence => [$key, $title]) {
            $modules[$key] = Module::create([
                'sequence' => $sequence,
                'key' => $key,
                'title' => $title,
                'description' => $title,
                'is_active' => true,
            ]);
        }

        return $modules;
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
