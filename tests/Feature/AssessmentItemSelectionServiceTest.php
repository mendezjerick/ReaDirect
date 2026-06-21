<?php

namespace Tests\Feature;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\Learner;
use App\Models\LearningContent;
use App\Models\School;
use App\Services\AssessmentItemSelectionService;
use App\Support\IsolatedLetterSet;
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

    public function test_it_locks_task_two_a_rhyme_decisions_with_required_split(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach (range(1, 10) as $sequence) {
            $isRhyme = $sequence <= 6;
            $wordTwo = $isRhyme ? 'hat' : 'dog';

            LearningContent::create([
                'content_type' => 'rhyme_decision',
                'title' => 'Rhyme decision '.$sequence,
                'prompt' => "cat, {$wordTwo}",
                'payload' => [
                    'source_csv_id' => sprintf('T2A-D%03d', $sequence),
                    'sequence' => $sequence,
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

        $items = app(AssessmentItemSelectionService::class)->selectTask2ARhymingPromptsForAttempt($attempt);

        $this->assertCount(10, $items);
        $this->assertSame(AssessmentItemSelectionService::TASK_2A_RHYME, $items->first()->task_type);
        $this->assertSame(6, $items->filter(fn ($item) => (bool) $item->prompt_snapshot['payload']['is_rhyme'])->count());
        $this->assertSame(4, $items->reject(fn ($item) => (bool) $item->prompt_snapshot['payload']['is_rhyme'])->count());
    }

    public function test_new_task_one_selection_excludes_unreliable_isolated_letters(): void
    {
        $attempt = $this->assessmentAttempt();
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        foreach ($letters as $letter) {
            LearningContent::create([
                'content_type' => 'letter',
                'title' => 'Letter '.$letter,
                'prompt' => $letter,
                'payload' => ['source_csv_id' => 'T1-'.$letter, 'expected_answer' => $letter],
                'accepted_answers' => [$letter],
                'difficulty' => 'easy',
                'is_active' => true,
            ]);
        }

        $items = app(AssessmentItemSelectionService::class)->selectTask1LettersForAttempt($attempt);
        $selected = $items->map(fn ($item) => $item->prompt_snapshot['payload']['expected_answer'] ?? $item->prompt_snapshot['prompt'])->all();

        $this->assertCount(10, $items);
        $this->assertEmpty(array_intersect(['B', 'P', 'D', 'T'], $selected));
        foreach (['C', 'L', 'Q', 'X', 'Z'] as $letter) {
            $this->assertContains($letter, IsolatedLetterSet::allowed());
        }
    }

    public function test_existing_task_one_selection_with_excluded_letters_is_reused_safely(): void
    {
        $attempt = $this->assessmentAttempt();

        AssessmentAttemptItem::create([
            'assessment_attempt_id' => $attempt->id,
            'task_type' => AssessmentItemSelectionService::TASK_1_LETTER,
            'sequence' => 1,
            'prompt_snapshot' => [
                'prompt' => 'B',
                'payload' => ['expected_answer' => 'B'],
                'accepted_answers' => ['B'],
            ],
            'selected_at' => now(),
        ]);

        $items = app(AssessmentItemSelectionService::class)->selectTask1LettersForAttempt($attempt);

        $this->assertCount(1, $items);
        $this->assertSame('B', $items->first()->prompt_snapshot['payload']['expected_answer']);
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

    public function test_it_lists_active_stories_and_locks_selected_reading_passage(): void
    {
        $attempt = $this->assessmentAttempt();

        foreach ([1 => true, 2 => true, 3 => false] as $sequence => $active) {
            LearningContent::create([
                'content_type' => 'reading_passage',
                'title' => 'Story '.$sequence,
                'prompt' => 'A short sample passage for testing only.',
                'payload' => [
                    'source_csv_id' => sprintf('PASS-%03d', $sequence),
                    'story_number' => $sequence,
                    'word_count' => 50,
                ],
                'difficulty' => 'easy',
                'is_active' => $active,
            ]);
        }

        $service = app(AssessmentItemSelectionService::class);

        $this->assertSame(['PASS-001', 'PASS-002'], $service->availableReadingPassages()->map(
            fn (LearningContent $content): string => $content->payload['source_csv_id']
        )->all());

        $selected = $service->selectReadingPassageBySourceCsvIdForAttempt($attempt, 'PASS-002');
        $selectedAgain = $service->selectedReadingPassageForAttempt($attempt);

        $this->assertSame('PASS-002', $selected->source_csv_id);
        $this->assertSame($selected->id, $selectedAgain?->id);
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
