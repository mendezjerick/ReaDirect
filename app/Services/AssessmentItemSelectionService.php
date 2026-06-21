<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\LearningContent;
use App\Support\IsolatedLetterSet;
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
        return $this->selectAndLock(
            $assessmentAttempt,
            self::TASK_1_LETTER,
            ['letter', 'crla_task_1_letter'],
            10,
            fn (LearningContent $content): bool => IsolatedLetterSet::isAllowed(IsolatedLetterSet::expectedLetterFromContent($content))
        );
    }

    public function selectTask2ARhymingPromptsForAttempt(AssessmentAttempt $assessmentAttempt): Collection
    {
        return DB::transaction(function () use ($assessmentAttempt): Collection {
            $existing = $this->getLockedItemsForAttempt($assessmentAttempt, self::TASK_2A_RHYME);

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $available = LearningContent::query()
                ->whereIn('content_type', ['rhyme_decision', 'crla_task_2a_rhyme_decision'])
                ->where('is_active', true)
                ->get()
                ->sortBy(fn (LearningContent $content): int => (int) ($content->payload['sequence'] ?? $content->id))
                ->values();

            $rhyming = $available
                ->filter(fn (LearningContent $content): bool => $this->payloadBoolean($content->payload['is_rhyme'] ?? false))
                ->take(6)
                ->values();
            $nonRhyming = $available
                ->reject(fn (LearningContent $content): bool => $this->payloadBoolean($content->payload['is_rhyme'] ?? false))
                ->take(4)
                ->values();

            if ($rhyming->count() < 6 || $nonRhyming->count() < 4) {
                throw new RuntimeException('Task 2A requires 6 active rhyming pairs and 4 active non-rhyming pairs.');
            }

            $items = $rhyming
                ->concat($nonRhyming)
                ->sortBy(fn (LearningContent $content): int => (int) ($content->payload['sequence'] ?? $content->id))
                ->values();

            $this->lockItemsForAttempt($assessmentAttempt, self::TASK_2A_RHYME, $items);

            return $this->getLockedItemsForAttempt($assessmentAttempt, self::TASK_2A_RHYME);
        });
    }

    public function selectTask2BWordSentenceItemsForAttempt(AssessmentAttempt $assessmentAttempt): Collection
    {
        return $this->selectAndLock($assessmentAttempt, self::TASK_2B_WORD_SENTENCE, ['word_sentence', 'crla_task_2b_word_sentence'], 10);
    }

    public function selectReadingPassageForAttempt(AssessmentAttempt $assessmentAttempt): ?AssessmentAttemptItem
    {
        return $this->selectAndLock($assessmentAttempt, self::READING_PASSAGE, ['reading_passage'], 1)->first();
    }

    public function availableReadingPassages(): Collection
    {
        return LearningContent::query()
            ->where('content_type', 'reading_passage')
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (LearningContent $content): array => [
                (int) ($content->payload['story_number'] ?? 999),
                $this->sourceCsvId($content) ?? '',
            ])
            ->values();
    }

    public function selectedReadingPassageForAttempt(AssessmentAttempt $assessmentAttempt): ?AssessmentAttemptItem
    {
        return $this->getLockedItemsForAttempt($assessmentAttempt, self::READING_PASSAGE)->first();
    }

    public function selectReadingPassageBySourceCsvIdForAttempt(AssessmentAttempt $assessmentAttempt, string $sourceCsvId): AssessmentAttemptItem
    {
        return DB::transaction(function () use ($assessmentAttempt, $sourceCsvId): AssessmentAttemptItem {
            $existing = $this->selectedReadingPassageForAttempt($assessmentAttempt);

            if ($existing) {
                if ($existing->source_csv_id !== $sourceCsvId) {
                    throw new RuntimeException('A reading story is already selected for this assessment attempt.');
                }

                return $existing;
            }

            $content = $this->availableReadingPassages()
                ->first(fn (LearningContent $row): bool => $this->sourceCsvId($row) === $sourceCsvId);

            if (! $content) {
                throw new RuntimeException('Selected reading story is not available.');
            }

            $this->lockItemsForAttempt($assessmentAttempt, self::READING_PASSAGE, collect([$content]));

            $selected = $this->selectedReadingPassageForAttempt($assessmentAttempt);

            if (! $selected) {
                throw new RuntimeException('Reading story could not be locked for this assessment attempt.');
            }

            return $selected;
        });
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
        int $requiredCount,
        ?callable $filter = null
    ): Collection {
        return DB::transaction(function () use ($assessmentAttempt, $taskType, $contentTypes, $requiredCount, $filter): Collection {
            $existing = $this->getLockedItemsForAttempt($assessmentAttempt, $taskType);

            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $query = LearningContent::query()
                ->whereIn('content_type', $contentTypes)
                ->where('is_active', true);

            $items = $filter === null
                ? $query->inRandomOrder()->limit($requiredCount)->get()
                : $query->get()->filter($filter)->shuffle()->take($requiredCount)->values();

            if ($items->count() < $requiredCount) {
                throw new RuntimeException("Not enough active {$taskType} items are available.");
            }

            $this->lockItemsForAttempt($assessmentAttempt, $taskType, $items->values());

            return $this->getLockedItemsForAttempt($assessmentAttempt, $taskType);
        });
    }

    private function lockItemsForAttempt(AssessmentAttempt $assessmentAttempt, string $taskType, Collection $items): void
    {
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

    private function payloadBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
