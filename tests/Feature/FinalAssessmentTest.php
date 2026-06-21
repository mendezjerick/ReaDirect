<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\AudioFile;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\Module;
use App\Models\School;
use App\Services\Assessment\FinalAssessmentComparisonService;
use App\Services\AssessmentItemSelectionService;
use App\Support\LearnerStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'task-1'), ['responses' => $responses])
            ->assertRedirect(route('final-assessment.task', 'task-2b'));

        $final->refresh();

        $this->assertSame(10, (int) $final->task_1_score);
        $this->assertSame(10, (int) $final->task_2a_score);
        $this->assertSame(10, AssessmentTaskResponse::where('assessment_attempt_id', $final->id)->count());
    }

    public function test_final_low_task_one_path_completes_after_task_two_a_without_task_two_b_or_passage(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();
        $this->seedTaskTwoADecisions();
        $this->withSession(['learner_id' => $learner->id])->post(route('final-assessment.start.store'));
        $final = AssessmentAttempt::where('attempt_type', 'final_reassessment')->firstOrFail();
        $taskOneResponses = $final->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => $item->sequence <= 5 ? $item->prompt_snapshot['prompt'] : 'wrong',
                'transcript_source' => 'manual',
            ])
            ->all();

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'task-1'), ['responses' => $taskOneResponses])
            ->assertRedirect(route('final-assessment.task', 'task-2a'));

        app(AssessmentItemSelectionService::class)->selectTask2ARhymingPromptsForAttempt($final->refresh());
        $taskTwoAResponses = $final->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_2A_RHYME)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => $item->prompt_snapshot['payload']['correct_answer'],
            ])
            ->all();

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'task-2a'), ['responses' => $taskTwoAResponses])
            ->assertRedirect(route('learner.completion'));

        $final->refresh();
        $this->assertSame('final_reassessment_completed', $final->status);
        $this->assertSame(5, (int) $final->task_1_score);
        $this->assertSame(10, (int) $final->task_2a_score);
        $this->assertSame(0, (int) $final->task_2b_score);
        $this->assertSame(15, (int) $final->crla_total_score);
        $this->assertSame(0.0, (float) $final->final_reading_score);

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id])
            ->get(route('final-assessment.task', 'task-2b'))
            ->assertRedirect(route('learner.completion'));
    }

    public function test_final_task_one_page_resumes_first_unanswered_item_from_database(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();
        $this->withSession(['learner_id' => $learner->id])->post(route('final-assessment.start.store'));
        $final = AssessmentAttempt::where('attempt_type', 'final_reassessment')->firstOrFail();
        $answeredItems = $final->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)
            ->orderBy('sequence')
            ->take(2)
            ->get();

        foreach ($answeredItems as $item) {
            $item->update(['answered_at' => now()]);
            AssessmentTaskResponse::create([
                'assessment_attempt_id' => $final->id,
                'learner_id' => $final->learner_id,
                'learning_content_id' => $item->learning_content_id,
                'assessment_attempt_item_id' => $item->id,
                'task_key' => $item->task_type,
                'task_type' => $item->task_type,
                'item_number' => $item->sequence,
                'prompt' => $item->prompt_snapshot['prompt'] ?? null,
                'expected_answer' => 'A',
                'learner_transcript' => 'A',
                'transcript_source' => 'stt_auto',
                'response_text' => 'A',
                'rule_applied' => 'DIAGNOSTIC_ITEM_PROGRESS_SAVE_V1',
            ]);
        }

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('final-assessment.task', 'task-1'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/FinalAssessment/Task1LetterPronunciation')
                ->where('initialIndex', 2)
                ->where('items.0.saved_response.answer', 'A')
                ->where('items.1.saved_response.answer', 'A')
            );
    }

    public function test_final_task_two_b_accepts_highlighted_word_responses(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();
        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'task_2b',
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'started_at' => now(),
        ]);

        foreach (range(1, 10) as $index) {
            $final->selectedItems()->create([
                'source_csv_id' => 'FINAL-T2B-'.$index,
                'task_type' => AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE,
                'sequence' => $index,
                'prompt_snapshot' => [
                    'prompt' => 'I see a cat.',
                    'payload' => ['target_word' => 'cat', 'expected_answer' => 'cat'],
                    'accepted_answers' => ['cat'],
                ],
                'selected_at' => now(),
            ]);
        }

        $responses = $final->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => [
                'assessment_attempt_item_id' => $item->id,
                'answer' => 'cat',
                'transcript_source' => 'manual',
            ])
            ->all();

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'task-2b'), ['responses' => $responses])
            ->assertRedirect(route('final-assessment.task', 'story-selection'));

        $this->assertGreaterThanOrEqual(8, (int) $final->refresh()->task_2b_score);
    }

    public function test_final_sandbox_passage_can_continue_with_saved_audio_and_manual_incorrect_word_override_when_asr_retries(): void
    {
        Storage::fake('local');
        config([
            'readirect_ai.enabled' => true,
            'readirect_ai.base_url' => 'http://ai.test',
            'readirect_ai.endpoints.analyze_audio' => '/analyze-audio',
        ]);
        Http::fake([
            'http://ai.test/analyze-audio' => Http::response([
                'ok' => true,
                'raw_transcript' => '',
                'corrected_transcript' => '',
                'displayed_transcript' => '',
                'retry_required' => true,
                'uncertain' => true,
                'learner_retry_message' => 'Please record again.',
                'audio_quality' => ['quality_flags' => ['too_short' => true]],
            ]),
        ]);

        [$learner] = $this->learnerWithBaselineDiagnostic();
        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'crla_completed',
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'is_sandbox' => true,
            'started_at' => now(),
        ]);
        $this->addReadingPassage($final);
        $audioFile = $this->audioFile($final);

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'passage'), [
                'incorrect_words' => 3,
                'audio_file_id' => $audioFile->id,
            ])
            ->assertRedirect(route('final-assessment.task', 'comprehension'));

        $final->refresh();
        $this->assertSame(3, (int) $final->incorrect_words);
        $this->assertSame(94.0, (float) $final->reading_accuracy);
    }

    public function test_final_sandbox_passage_manual_incorrect_word_override_does_not_require_audio(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();
        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'crla_completed',
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'is_sandbox' => true,
            'started_at' => now(),
        ]);
        $this->addReadingPassage($final);

        $this->withSession(['learner_id' => $learner->id, 'final_assessment_attempt_id' => $final->id, 'admin_testing_mode' => true])
            ->post(route('final-assessment.task.submit', 'passage'), [
                'incorrect_words' => 3,
            ])
            ->assertRedirect(route('final-assessment.task', 'comprehension'));

        $final->refresh();
        $this->assertSame(3, (int) $final->incorrect_words);
        $this->assertSame(94.0, (float) $final->reading_accuracy);
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

    public function test_completion_screen_recovers_completed_final_attempt_without_session_and_shows_safe_summary(): void
    {
        [$learner] = $this->learnerWithCompletedFinal();

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.completion'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Completion')
                ->where('resultSummary.cards.0.title', 'Initial Reading Check')
                ->where('resultSummary.cards.1.title', 'Final Reading Check')
                ->where('resultSummary.cards.2.title', 'Progress')
                ->where('agentMessages.0.name', 'Miss Vivian')
                ->where('agentMessages.0.message', 'You did a wonderful job completing your reading assessments. Thank you for trying your best.')
                ->where('agentMessages.1.name', 'Miss Ciel')
                ->where('agentMessages.1.message', 'I am proud of your practice. You worked hard and kept going. Great job!')
                ->where('agentMessages.2.name', 'Miss Estelle')
                ->where('agentMessages.2.message', 'Great job finishing your final reading check. You completed your reading journey.')
                ->missing('comparison_summary')
                ->missing('attempt_id')
            );
    }

    public function test_thank_you_marks_final_reassessment_completed_learner_completed_and_redirects_home(): void
    {
        [$learner] = $this->learnerWithCompletedFinal();

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.completion.thank-you'))
            ->assertRedirect(route('welcome'));

        $this->assertSame(LearnerStage::COMPLETED, $learner->refresh()->current_stage);
    }

    public function test_thank_you_cannot_mark_completion_before_final_reassessment_completion(): void
    {
        [$learner] = $this->learnerWithBaselineDiagnostic();

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.completion.thank-you'))
            ->assertRedirect(route('learner.dashboard'));

        $this->assertSame(LearnerStage::FINAL_REASSESSMENT_PENDING, $learner->refresh()->current_stage);
    }

    public function test_completion_routes_require_current_learner_session_and_do_not_fall_back_to_first_learner(): void
    {
        [$firstLearner] = $this->learnerWithCompletedFinal(['current_stage' => LearnerStage::FINAL_REASSESSMENT_COMPLETED]);

        $this->get(route('learner.dashboard'))
            ->assertRedirect(route('learner.access'));

        $this->get(route('learner.completion'))
            ->assertRedirect(route('learner.access'));

        $this->post(route('learner.completion.thank-you'))
            ->assertRedirect(route('learner.access'));

        $this->assertSame(LearnerStage::FINAL_REASSESSMENT_COMPLETED, $firstLearner->refresh()->current_stage);
    }

    public function test_completed_learner_dashboard_points_to_completion_not_restart_actions(): void
    {
        [$learner] = $this->learnerWithCompletedFinal(['current_stage' => LearnerStage::COMPLETED]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/Dashboard')
                ->where('flowState.stage', LearnerStage::COMPLETED)
                ->where('flowState.primary_action_label', 'View Completion')
                ->where('flowState.primary_action_route', route('learner.completion'))
                ->missing('learner.id')
                ->missing('learner.current_module_id')
                ->missing('flowState.diagnostic.attempt_id')
                ->missing('flowState.final_reassessment.attempt_id')
            );
    }

    public function test_completed_learner_cannot_restart_diagnostic_final_or_module_by_url(): void
    {
        [$learner, $module] = $this->learnerWithCompletedFinal(['current_stage' => LearnerStage::COMPLETED]);

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.diagnostic.start'))
            ->assertRedirect(route('learner.completion'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('final-assessment.start'))
            ->assertRedirect(route('learner.completion'));

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.modules.activity', [$module, 'hear_and_repeat']))
            ->assertRedirect(route('learner.completion'));
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

    private function addReadingPassage(AssessmentAttempt $attempt): void
    {
        $content = LearningContent::create([
            'content_type' => 'reading_passage',
            'title' => 'Final Passage',
            'prompt' => 'Leo can read. Leo can run. Leo can hop.',
            'payload' => ['source_csv_id' => 'FINAL-PASS-1'],
            'accepted_answers' => [],
            'difficulty' => 'easy',
            'is_active' => true,
        ]);

        $attempt->selectedItems()->create([
            'learning_content_id' => $content->id,
            'source_csv_id' => 'FINAL-PASS-1',
            'task_type' => AssessmentItemSelectionService::READING_PASSAGE,
            'sequence' => 1,
            'prompt_snapshot' => [
                'prompt' => $content->prompt,
                'payload' => $content->payload,
                'accepted_answers' => [],
            ],
            'selected_at' => now(),
        ]);
    }

    private function seedTaskTwoADecisions(): void
    {
        foreach (range(1, 10) as $index) {
            $isRhyme = $index <= 6;
            $wordTwo = $isRhyme ? 'hat' : 'dog';

            LearningContent::create([
                'content_type' => 'rhyme_decision',
                'title' => 'Rhyme decision '.$index,
                'prompt' => "cat, {$wordTwo}",
                'payload' => [
                    'source_csv_id' => 'FINAL-T2A-'.$index,
                    'sequence' => $index,
                    'word_1' => 'cat',
                    'word_2' => $wordTwo,
                    'is_rhyme' => $isRhyme,
                    'correct_answer' => $isRhyme ? 'yes' : 'no',
                    'audio_script' => "cat, {$wordTwo}",
                ],
                'accepted_answers' => [$isRhyme ? 'yes' : 'no'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }
    }

    private function audioFile(AssessmentAttempt $attempt): AudioFile
    {
        $path = 'audio/learners/'.$attempt->learner->public_id.'/final-passage.webm';
        Storage::disk('local')->put($path, 'fake-audio');

        return AudioFile::create([
            'learner_id' => $attempt->learner_id,
            'assessment_attempt_id' => $attempt->id,
            'disk' => 'local',
            'path' => $path,
            'file_path' => $path,
            'mime_type' => 'audio/webm',
            'size_bytes' => 10,
            'file_size' => 10,
            'file_hash' => hash('sha256', 'fake-audio'),
            'recording_context' => 'final_passage_reading',
            'sync_status' => 'synced',
        ]);
    }

    private function learnerWithCompletedFinal(array $learnerOverrides = []): array
    {
        [$learner, $baseline] = $this->learnerWithBaselineDiagnostic();
        $module = Module::firstOrFail();
        $learner->update(array_merge([
            'current_stage' => LearnerStage::FINAL_REASSESSMENT_COMPLETED,
            'current_module_id' => null,
        ], $learnerOverrides));

        $final = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'baseline_assessment_attempt_id' => $baseline->id,
            'attempt_type' => 'final_reassessment',
            'status' => 'final_reassessment_completed',
            'task_1_score' => 8,
            'task_2a_score' => 8,
            'task_2b_score' => 8,
            'crla_total_score' => 24,
            'crla_classification' => 'Light Refresher',
            'reading_accuracy' => 82,
            'comprehension_percentage' => 80,
            'final_reading_score' => 81,
            'reading_classification' => 'Independent Reader',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $final->update([
            'comparison_summary' => app(FinalAssessmentComparisonService::class)->compareAttempts($baseline, $final),
        ]);

        return [$learner->refresh(), $module, $final];
    }
}
