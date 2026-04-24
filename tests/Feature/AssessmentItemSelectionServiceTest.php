<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\School;
use App\Services\AssessmentItemSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentItemSelectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_locks_task_one_items_and_reuses_existing_selection(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach (range(1, 12) as $sequence) {
            LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter '.$sequence,
                'prompt' => chr(64 + $sequence),
                'payload' => ['source_csv_id' => sprintf('T1-L%03d', $sequence)],
                'accepted_answers' => [chr(64 + $sequence)],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }

        $service = app(AssessmentItemSelectionService::class);
        $firstSelection = $service->selectTask1LettersForAttempt($attempt);
        $secondSelection = $service->selectTask1LettersForAttempt($attempt);

        $this->assertCount(10, $firstSelection);
        $this->assertSame(
            $firstSelection->pluck('id')->all(),
            $secondSelection->pluck('id')->all()
        );
        $this->assertSame(range(1, 10), $firstSelection->pluck('sequence')->all());
        $this->assertNotNull($firstSelection->first()->prompt_snapshot);
    }

    public function test_it_locks_task_two_a_rhyme_prompts(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach (range(1, 12) as $sequence) {
            LearningContent::create([
                'content_type' => 'rhyme_prompt',
                'title' => 'Rhyme '.$sequence,
                'prompt' => 'cat',
                'payload' => ['source_csv_id' => sprintf('T2A-R%03d', $sequence)],
                'accepted_answers' => ['bat', 'hat'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }

        $items = app(AssessmentItemSelectionService::class)->selectTask2ARhymingPromptsForAttempt($attempt);

        $this->assertCount(10, $items);
        $this->assertSame(AssessmentItemSelectionService::TASK_2A_RHYME, $items->first()->task_type);
    }

    public function test_it_locks_task_two_b_word_sentence_items(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach (range(1, 12) as $sequence) {
            LearningContent::create([
                'content_type' => 'word_sentence',
                'title' => 'Sentence '.$sequence,
                'prompt' => 'I see a cat.',
                'payload' => ['source_csv_id' => sprintf('T2B-W%03d', $sequence), 'target_word' => 'cat'],
                'accepted_answers' => ['cat'],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }

        $items = app(AssessmentItemSelectionService::class)->selectTask2BWordSentenceItemsForAttempt($attempt);

        $this->assertCount(10, $items);
        $this->assertSame(AssessmentItemSelectionService::TASK_2B_WORD_SENTENCE, $items->first()->task_type);
    }

    public function test_it_locks_one_reading_passage(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach (range(1, 3) as $sequence) {
            LearningContent::create([
                'content_type' => 'reading_passage',
                'title' => 'Passage '.$sequence,
                'prompt' => 'A short sample passage for testing only.',
                'payload' => ['source_csv_id' => sprintf('PASS-%03d', $sequence)],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }

        $service = app(AssessmentItemSelectionService::class);
        $passage = $service->selectReadingPassageForAttempt($attempt);
        $locked = $service->getLockedItemsForAttempt($attempt, AssessmentItemSelectionService::READING_PASSAGE);

        $this->assertNotNull($passage);
        $this->assertCount(1, $locked);
        $this->assertSame($passage->id, $locked->first()->id);
    }

    private function assessmentAttempt(): AssessmentAttempt
    {
        $school = School::create(['name' => 'Selection Test School']);
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
}
