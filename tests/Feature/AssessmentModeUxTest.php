<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\School;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssessmentModeUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_learner_assessment_pages_receive_safe_mode_props(): void
    {
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-1'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Task1LetterPronunciation')
                ->where('assessmentMode.isDeveloperQaMode', false)
                ->where('assessmentMode.canUseManualFallback', true)
                ->where('assessmentMode.canUseIncorrectWordsOverride', false)
                ->where('assessmentMode.canShowAssessmentDebug', false)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
                ->where('assessmentMode.canBypassLinearFlow', false)
                ->where('assessmentMode.canAutoTranscribeOnStop', false)
                ->where('assessmentMode.canForceLearnerStage', false)
                ->where('assessmentMode.canResetLearnerFlow', false)
                ->where('assessmentMode.canSeeRawAiPayload', false)
                ->where('assessmentMode.requireReviewBeforeSubmit', true)
            );
    }

    public function test_configured_admin_qa_mode_receives_only_enabled_permissions(): void
    {
        config([
            'readirect.developer_qa.enabled' => true,
            'readirect.developer_qa.manual_fallback' => true,
            'readirect.developer_qa.jump_controls' => true,
            'readirect.developer_qa.auto_transcribe_on_stop' => true,
            'readirect.developer_qa.show_ai_debug' => true,
            'readirect.developer_qa.flow_bypass' => false,
        ]);

        $admin = $this->userWithRole('system_admin');
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $this->actingAs($admin)
            ->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-1'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Task1LetterPronunciation')
                ->where('assessmentMode.isDeveloperQaMode', true)
                ->where('assessmentMode.canUseManualFallback', true)
                ->where('assessmentMode.canUseIncorrectWordsOverride', true)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
                ->where('assessmentMode.canAutoTranscribeOnStop', false)
                ->where('assessmentMode.canSeeRawAiPayload', true)
                ->where('assessmentMode.canBypassLinearFlow', false)
                ->where('assessmentMode.requireReviewBeforeSubmit', true)
            );
    }

    public function test_production_safe_defaults_do_not_enable_qa_for_admin_or_query_string(): void
    {
        config([
            'app.env' => 'production',
            'readirect.developer_qa.enabled' => false,
            'readirect.developer_qa.manual_fallback' => false,
            'readirect.developer_qa.jump_controls' => false,
            'readirect.developer_qa.auto_transcribe_on_stop' => false,
            'readirect_ai.debug.enable_developer_assessment_reset' => false,
        ]);

        $admin = $this->userWithRole('system_admin');
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $this->actingAs($admin)
            ->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
            ->get(route('learner.diagnostic.task-1', ['admin_testing' => 1, 'qa' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Task1LetterPronunciation')
                ->where('assessmentMode.isDeveloperQaMode', false)
                ->where('assessmentMode.canUseManualFallback', true)
                ->where('assessmentMode.canUseIncorrectWordsOverride', false)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
                ->where('assessmentMode.canAutoTranscribeOnStop', false)
                ->where('assessmentMode.requireReviewBeforeSubmit', true)
            );
    }

    public function test_developer_qa_mode_keeps_manual_fallback_props(): void
    {
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $this->withSession([
            'learner_id' => $attempt->learner_id,
            'assessment_attempt_id' => $attempt->id,
            'admin_testing_mode' => true,
        ])
            ->get(route('learner.diagnostic.task-1'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Task1LetterPronunciation')
                ->where('assessmentMode.isDeveloperQaMode', true)
                ->where('assessmentMode.canUseManualFallback', true)
                ->where('assessmentMode.canUseIncorrectWordsOverride', true)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
                ->where('assessmentMode.canAutoTranscribeOnStop', false)
                ->where('assessmentMode.canSeeRawAiPayload', true)
                ->where('assessmentMode.requireReviewBeforeSubmit', true)
            );
    }

    public function test_normal_final_reassessment_pages_receive_safe_mode_props(): void
    {
        $learner = $this->learner(['current_stage' => LearnerStage::FINAL_REASSESSMENT_IN_PROGRESS]);
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_1',
            'started_at' => now(),
        ]);
        $this->attachItems($attempt, AssessmentItemSelectionService::TASK_1_LETTER);

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $attempt->id])
            ->get(route('final-assessment.task', 'task-1'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/FinalAssessment/Task1LetterPronunciation')
                ->where('assessmentMode.canUseManualFallback', true)
                ->where('assessmentMode.canUseIncorrectWordsOverride', false)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
            );
    }

    public function test_normal_learner_manual_transcript_scores(): void
    {
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $responses = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => 'A',
                'transcript_source' => 'manual',
            ])
            ->all();

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $this->assertSame(10, (int) $attempt->refresh()->task_1_score);
        $this->assertSame(10, AssessmentTaskResponse::count());
    }

    public function test_developer_qa_manual_transcript_still_scores(): void
    {
        $attempt = $this->diagnosticAttemptWithTaskItems(AssessmentItemSelectionService::TASK_1_LETTER);

        $responses = $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => 'A',
                'transcript_source' => 'manual',
            ])
            ->all();

        $this->withSession(['learner_id' => $attempt->learner_id, 'assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $this->assertSame(10, (int) $attempt->refresh()->task_1_score);
    }

    public function test_normal_audio_upload_omits_raw_debug_fields(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'A']);
        $learner = $this->learner();

        $response = $this->withSession(['learner_id' => $learner->id])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('letter.webm', 100, 'audio/webm'),
                'context_type' => 'assessment_task',
                'duration_seconds' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('transcript', 'A');

        $payload = $response->json();
        $this->assertArrayNotHasKey('raw_transcript', $payload);
        $this->assertArrayNotHasKey('model_used', $payload);
        $this->assertArrayNotHasKey('ai_error', $payload);
    }

    public function test_qa_audio_upload_returns_raw_debug_only_when_allowed(): void
    {
        Storage::fake('local');
        config(['stt.mock.transcript' => 'A']);
        $learner = $this->learner();

        $response = $this->withSession(['learner_id' => $learner->id, 'admin_testing_mode' => true])
            ->postJson(route('learner.audio.upload'), [
                'audio' => UploadedFile::fake()->create('letter.webm', 100, 'audio/webm'),
                'context_type' => 'assessment_task',
                'duration_seconds' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('transcript', 'A');

        $this->assertArrayHasKey('raw_transcript', $response->json());
        $this->assertArrayHasKey('stt_confidence', $response->json());
    }

    public function test_admin_testing_exit_clears_qa_session_state(): void
    {
        $admin = $this->userWithRole('system_admin');
        $learner = $this->learner();

        $this->actingAs($admin)
            ->withSession([
                'admin_testing_mode' => true,
                'admin_testing_learner_id' => $learner->id,
                'admin_testing_assessment_attempt_id' => 123,
                'admin_testing_module_attempt_id' => 456,
                'learner_id' => $learner->id,
                'assessment_attempt_id' => 123,
                'final_assessment_attempt_id' => 789,
                'module_attempt_id' => 456,
                'task_one_route' => ['next_task' => 'task_2b'],
            ])
            ->post(route('admin.testing.exit'))
            ->assertRedirect(route('admin.testing.index'))
            ->assertSessionMissing('admin_testing_mode')
            ->assertSessionMissing('admin_testing_learner_id')
            ->assertSessionMissing('admin_testing_assessment_attempt_id')
            ->assertSessionMissing('admin_testing_module_attempt_id')
            ->assertSessionMissing('learner_id')
            ->assertSessionMissing('assessment_attempt_id')
            ->assertSessionMissing('final_assessment_attempt_id')
            ->assertSessionMissing('module_attempt_id')
            ->assertSessionMissing('task_one_route');
    }

    public function test_learner_access_clears_stale_qa_session_state(): void
    {
        $learner = $this->learner();

        $this->withSession([
            'admin_testing_mode' => true,
            'admin_testing_learner_id' => 999,
            'admin_testing_assessment_attempt_id' => 123,
            'admin_testing_module_attempt_id' => 456,
            'assessment_attempt_id' => 123,
            'final_assessment_attempt_id' => 789,
            'module_attempt_id' => 456,
            'task_one_route' => ['next_task' => 'task_2b'],
        ])
            ->post(route('learner.access.store'), ['learner_code' => $learner->learner_code])
            ->assertRedirect(route('learner.diagnostic.start'))
            ->assertSessionHas('learner_id', $learner->id)
            ->assertSessionMissing('admin_testing_mode')
            ->assertSessionMissing('admin_testing_learner_id')
            ->assertSessionMissing('admin_testing_assessment_attempt_id')
            ->assertSessionMissing('admin_testing_module_attempt_id')
            ->assertSessionMissing('assessment_attempt_id')
            ->assertSessionMissing('final_assessment_attempt_id')
            ->assertSessionMissing('module_attempt_id')
            ->assertSessionMissing('task_one_route');
    }

    private function diagnosticAttemptWithTaskItems(string $taskType): AssessmentAttempt
    {
        $learner = $this->learner(['current_stage' => LearnerStage::DIAGNOSTIC_IN_PROGRESS]);
        $attempt = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'task_1',
            'started_at' => now(),
        ]);
        $this->attachItems($attempt, $taskType);

        return $attempt;
    }

    private function attachItems(AssessmentAttempt $attempt, string $taskType): void
    {
        foreach (range(1, 10) as $index) {
            $content = LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter A '.$index,
                'prompt' => 'A',
                'payload' => ['source_csv_id' => 'UX-'.$taskType.'-'.$index, 'expected_answer' => 'A'],
                'accepted_answers' => ['A', 'a'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            $attempt->selectedItems()->create([
                'learning_content_id' => $content->id,
                'source_csv_id' => 'UX-'.$taskType.'-'.$index,
                'task_type' => $taskType,
                'sequence' => $index,
                'prompt_snapshot' => [
                    'prompt' => 'A',
                    'payload' => $content->payload,
                    'accepted_answers' => $content->accepted_answers,
                ],
                'selected_at' => now(),
            ]);
        }
    }

    private function learner(array $attributes = []): Learner
    {
        $school = School::first() ?? School::create(['name' => 'Assessment Mode Test School']);

        return Learner::create(array_merge([
            'school_id' => $school->id,
            'learner_code' => uniqid('UX-', false),
            'first_name' => 'Mode',
            'grade_level' => 'Grade 1',
        ], $attributes));
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
}
