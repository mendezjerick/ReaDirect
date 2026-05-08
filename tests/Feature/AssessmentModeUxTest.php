<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\School;
use App\Services\AssessmentItemSelectionService;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->where('assessmentMode.canUseManualFallback', false)
                ->where('assessmentMode.canShowAssessmentDebug', false)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
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
                ->where('assessmentMode.canUseDeveloperJumpControls', true)
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
                ->where('assessmentMode.canUseManualFallback', false)
                ->where('assessmentMode.canUseDeveloperJumpControls', false)
            );
    }

    public function test_normal_learner_manual_transcript_is_not_used_for_scoring(): void
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
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, AssessmentTaskResponse::count());
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
}
