<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\LearningContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentItemSelectionService
{
    public const TASK_1_LETTER = 'task_1_letter_pronunciation';
    public const TASK_2A_RHYME = 'task_2a_rhyming_words';
    public const TASK_2B_WORD_SENTENCE = 'task_2b_word_in_sentence';
    public const READING_PASSAGE = 'reading_passage';

    public function selectTask1LettersForAttempt(AssessmentAttempt $assessmentAttempt): Collection
    {
        return $this->selectAndLock($assessmentAttempt, self::TASK_1_LETTER, ['letter', 'crla_task_1_letter'], 10);
    }

    public function selectTask2ARhymingPromptsForAttempt(AssessmentAttempt $assessmentAttempt): Collection
    {
        return $this->selectAndLock($assessmentAttempt, self::TASK_2A_RHYME, ['rhyme_prompt', 'crla_task_2a_rhyme'], 10);
    }

    public function selectTask2BWordSentenceItemsForAttempt(AssessmentAttempt $assessmentAttempt): Collection
    {
        return $this->selectAndLock($assessmentAttempt, self::TASK_2B_WORD_SENTENCE, ['word_sentence', 'crla_task_2b_word_sentence'], 10);
    }

    public function selectReadingPassageForAttempt(AssessmentAttempt $assessmentAttempt): ?AssessmentAttemptItem
    {
        return $this->selectAndLock($assessmentAttempt, self::READING_PASSAGE, ['reading_passage'], 1)->first();
    }

    public function getLockedItemsForAttempt(AssessmentAttempt $assessmentAttempt, string $taskType): Collection
    {
        return AssessmentAttemptItem::query()
            ->where('assessment_attempt_id', $assessmentAttempt->id)
            ->where('task_type', $taskType)
            ->orderBy('sequence')
            ->get();
    }

    /**
     * CSV rows are expected to be imported into learning_contents later.
     * This service locks selected LearningContent rows so repeated page loads
     * reuse the same prompts for the assessment attempt.
     */
    private function selectAndLock(
        AssessmentAttempt $assessmentAttempt,
        string $taskType,
        array $contentTypes,
        int $requiredCount
    ): Collection {
        return DB::transaction(function () use ($assessmentAttempt, $taskType, $contentTypes, $requiredCount): Collection {
            $existing = $this->getLockedItemsForAttempt($assessmentAttempt, $taskType);

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $items = LearningContent::query()
                ->whereIn('content_type', $contentTypes)
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($requiredCount)
                ->get();

            if ($items->count() < $requiredCount) {
                throw new RuntimeException("Not enough active {$taskType} items are available.");
            }

            $now = now();

            $items->values()->each(function (LearningContent $content, int $index) use ($assessmentAttempt, $taskType, $now): void {
                AssessmentAttemptItem::create([
                    'assessment_attempt_id' => $assessmentAttempt->id,
                    'learning_content_id' => $content->id,
                    'source_csv_id' => $this->sourceCsvId($content),
                    'task_type' => $taskType,
                    'sequence' => $index + 1,
                    'prompt_snapshot' => $this->snapshot($content),
                    'selected_at' => $now,
                ]);
            });

            return $this->getLockedItemsForAttempt($assessmentAttempt, $taskType);
        });
    }

    private function snapshot(LearningContent $content): array
    {
        return [
            'learning_content_id' => $content->id,
            'source_csv_id' => $this->sourceCsvId($content),
            'content_type' => $content->content_type,
            'title' => $content->title,
            'prompt' => $content->prompt,
            'payload' => $content->payload,
            'accepted_answers' => $content->accepted_answers,
            'difficulty' => $content->difficulty,
        ];
    }

    private function sourceCsvId(LearningContent $content): ?string
    {
        return $content->payload['source_csv_id'] ?? $content->payload['csv_id'] ?? null;
    }
}
