<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\School;
use App\Models\User;
use App\Services\AssessmentItemSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiagnosticAssessmentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_one_submission_with_missing_answer_is_rejected(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_1_LETTER, 'letter');
        $responses = $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_1_LETTER, 'A');
        $responses[0]['answer'] = '';

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');

        $this->assertSame(0, AssessmentTaskResponse::count());
    }

    public function test_task_one_submission_with_all_answers_is_accepted(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_1_LETTER, 'letter');

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), [
                'responses' => $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_1_LETTER, 'A'),
            ])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $this->assertSame(10, $attempt->refresh()->task_1_score);
    }

    public function test_incorrect_nonblank_answers_can_score_zero(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_1_LETTER, 'letter');

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-1.store'), [
                'responses' => $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_1_LETTER, 'zzz'),
            ])
            ->assertRedirect(route('learner.diagnostic.task-routing'));

        $this->assertSame(0, $attempt->refresh()->task_1_score);
        $this->assertSame(10, AssessmentTaskResponse::count());
    }

    public function test_task_two_a_submission_with_missing_answer_is_rejected(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_2A_RHYME, 'rhyme_prompt');
        $responses = $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_2A_RHYME, 'bat');
        $responses[0]['answer'] = ' ';

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-2a.store'), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');
    }

    public function test_task_two_a_scores_the_paired_second_word_only(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_2A_RHYME, 'rhyme_prompt');

        $attempt->selectedItems()
            ->where('task_type', AssessmentItemSelectionService::TASK_2A_RHYME)
            ->get()
            ->each(function ($item): void {
                $snapshot = $item->prompt_snapshot;
                $snapshot['prompt'] = 'cat';
                $snapshot['payload'] = array_merge($snapshot['payload'] ?? [], [
                    'target_word' => 'bat',
                    'expected_answer' => 'bat',
                ]);
                $snapshot['accepted_answers'] = ['bat', 'hat', 'mat'];
                $item->update(['prompt_snapshot' => $snapshot]);
            });

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-2a.store'), [
                'responses' => $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_2A_RHYME, 'hat'),
            ])
            ->assertRedirect(route('learner.diagnostic.task-2b'));

        $this->assertSame(0, (int) $attempt->refresh()->task_2a_score);
    }

    public function test_task_two_b_submission_with_missing_answer_is_rejected(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE, 'word_sentence');
        $responses = $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE, 'cat');
        $responses[0]['answer'] = '';

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-2b.store'), ['responses' => $responses])
            ->assertSessionHasErrors('responses.0.answer');
    }

    public function test_task_two_b_scores_the_highlighted_word_not_the_full_sentence(): void
    {
        $attempt = $this->attemptWithLockedItems(AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE, 'word_sentence');
        $attempt->update(['task_1_score' => 10, 'task_2a_score' => 10]);

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.task-2b.store'), [
                'responses' => $this->responsesFor($attempt, AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE, 'cat'),
            ])
            ->assertRedirect(route('learner.diagnostic.crla-summary'));

        $this->assertGreaterThanOrEqual(8, (int) $attempt->refresh()->task_2b_score);
    }

    public function test_passage_reading_missing_incorrect_words_is_rejected(): void
    {
        $attempt = $this->assessmentAttempt();
        $attempt->update([
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'status' => 'crla_completed',
        ]);

        $this->withSession(['assessment_attempt_id' => $attempt->id, 'admin_testing_mode' => true])
            ->post(route('learner.diagnostic.passage.store'), [])
            ->assertSessionHasErrors('incorrect_words');
    }

    public function test_comprehension_submission_with_fewer_than_five_answers_is_rejected(): void
    {
        $attempt = $this->assessmentAttempt();
        $attempt->update([
            'task_1_score' => 10,
            'task_2a_score' => 10,
            'task_2b_score' => 10,
            'crla_total_score' => 30,
            'reading_accuracy' => 90,
            'status' => 'passage_completed',
        ]);

        $this->withSession(['assessment_attempt_id' => $attempt->id])
            ->post(route('learner.diagnostic.comprehension.store'), [
                'responses' => [
                    ['question_id' => 'CQ-001', 'answer' => 'park'],
                ],
            ])
            ->assertSessionHasErrors('responses');
    }

    public function test_normal_learner_cannot_see_or_use_developer_retest(): void
    {
        config(['readirect_ai.debug.enable_developer_assessment_reset' => false]);

        $learner = $this->learner();

        $this->withSession(['learner_id' => $learner->id])
            ->get(route('learner.diagnostic.start'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/DiagnosticStart')
                ->where('developerRetest.enabled', false)
            );

        $this->withSession(['learner_id' => $learner->id])
            ->post(route('learner.diagnostic.developer-retest'))
            ->assertForbidden();
    }

    public function test_admin_developer_can_start_new_diagnostic_attempt_and_preserve_previous_attempt(): void
    {
        config(['readirect_ai.debug.enable_developer_assessment_reset' => false]);
        $this->seedTaskOneLetters();
        $admin = $this->admin();
        $learner = $this->learner();
        $previous = AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'module_placement_completed',
            'is_sandbox' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['learner_id' => $learner->id, 'admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->get(route('learner.diagnostic.start'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Learner/DiagnosticStart')
                ->where('developerRetest.enabled', true)
            );

        $this->actingAs($admin)
            ->withSession(['learner_id' => $learner->id, 'admin_testing_mode' => true, 'admin_testing_learner_id' => $learner->id])
            ->post(route('learner.diagnostic.developer-retest'))
            ->assertRedirect(route('learner.diagnostic.task-1'));

        $this->assertDatabaseHas('assessment_attempts', ['id' => $previous->id, 'status' => 'module_placement_completed']);
        $newAttempt = AssessmentAttempt::where('learner_id', $learner->id)->where('id', '!=', $previous->id)->latest()->firstOrFail();
        $this->assertTrue($newAttempt->is_sandbox);
        $this->assertSame('task_1', $newAttempt->status);
        $this->assertSame(10, $newAttempt->selectedItems()->where('task_type', AssessmentItemSelectionService::TASK_1_LETTER)->count());
    }

    private function attemptWithLockedItems(string $taskType, string $contentType): AssessmentAttempt
    {
        $attempt = $this->assessmentAttempt();

        if ($taskType === AssessmentItemSelectionService::TASK_2A_RHYME) {
            $attempt->update(['task_1_score' => 5, 'status' => 'task_1_completed']);
        }

        if ($taskType === AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE) {
            $attempt->update(['task_1_score' => 10, 'task_2a_score' => 10, 'status' => 'task_1_completed']);
        }

        foreach (range(1, 10) as $index) {
            $content = LearningContent::create([
                'content_type' => $contentType,
                'title' => 'Item '.$index,
                'prompt' => $contentType === 'word_sentence' ? 'I see a cat.' : 'A',
                'payload' => ['source_csv_id' => 'SRC-'.$index, 'target_word' => 'cat', 'expected_answer' => 'A'],
                'accepted_answers' => $contentType === 'rhyme_prompt' ? ['bat'] : [$contentType === 'word_sentence' ? 'cat' : 'A'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            $attempt->selectedItems()->create([
                'learning_content_id' => $content->id,
                'source_csv_id' => 'SRC-'.$index,
                'task_type' => $taskType,
                'sequence' => $index,
                'prompt_snapshot' => [
                    'prompt' => $content->prompt,
                    'payload' => $content->payload,
                    'accepted_answers' => $content->accepted_answers,
                ],
                'selected_at' => now(),
            ]);
        }

        return $attempt;
    }

    private function responsesFor(AssessmentAttempt $attempt, string $taskType, string $answer): array
    {
        return $attempt->selectedItems()
            ->where('task_type', $taskType)
            ->orderBy('sequence')
            ->get()
            ->map(fn ($item) => ['assessment_attempt_item_id' => $item->id, 'answer' => $answer])
            ->all();
    }

    private function assessmentAttempt(): AssessmentAttempt
    {
        $school = School::create(['name' => 'Validation Test School']);
        $learner = Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('RD-', false),
            'first_name' => 'Test',
            'grade_level' => 'Grade 1',
        ]);

        return AssessmentAttempt::create([
            'learner_id' => $learner->id,
            'attempt_type' => 'diagnostic',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function learner(): Learner
    {
        $school = School::first() ?? School::create(['name' => 'Developer Retest School']);

        return Learner::create([
            'school_id' => $school->id,
            'learner_code' => uniqid('QA-', false),
            'first_name' => 'QA',
            'grade_level' => 'Grade 1',
        ]);
    }

    private function admin(): User
    {
        Role::findOrCreate('system_admin');
        $user = User::create([
            'name' => 'System Admin',
            'email' => uniqid('admin-', false).'@example.test',
            'password' => 'password',
        ]);
        $user->assignRole('system_admin');

        return $user;
    }

    private function seedTaskOneLetters(): void
    {
        foreach (range('A', 'J') as $letter) {
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
}
