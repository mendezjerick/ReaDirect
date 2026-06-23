<?php

namespace Database\Seeders;

use App\Models\LearningContent;
use Illuminate\Database\Seeder;

class DiagnosticContentSeeder extends Seeder
{
    private string $basePath;

    private ?array $enrichmentIndex = null;

    public function __construct()
    {
        $this->basePath = database_path('seed-data/readirect');
    }

    public function run(): void
    {
        $this->seedTaskOneLetters();
        $this->seedTaskTwoARhymes();
        $this->seedTaskTwoBWords();
        $this->seedReadingPassages();
        $this->seedComprehensionQuestions();
    }

    private function seedTaskOneLetters(): void
    {
        foreach ($this->csv('task1_letter_pronunciation.csv') as $row) {
            $metadata = $this->metadata($row);
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));
            $this->updateLearningContent(
                'letter',
                $this->rowId($row),
                [
                    'title' => 'Letter '.$this->promptText($row, $metadata),
                    'prompt' => $this->promptText($row, $metadata),
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'sequence' => $this->sequence($row, $metadata),
                        'expected_answer' => $this->expectedAnswer($row, $metadata),
                        'expected_phoneme' => $metadata['expected_phoneme'] ?? $row['expected_phoneme'] ?? $row['expected_phonemes'] ?? null,
                        'points' => $this->points($row, $metadata),
                        'enrichment' => $enrichment,
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedTaskTwoARhymes(): void
    {
        foreach ($this->csv('task2a_rhyming_words.csv') as $row) {
            $metadata = $this->metadata($row);
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));
            $wordOne = $this->taskTwoAWordOne($row, $metadata);
            $wordTwo = $this->taskTwoAWordTwo($row, $metadata);
            $isRhyme = $this->taskTwoAIsRhyme($row, $metadata);
            $correctAnswer = $this->taskTwoACorrectAnswer($row, $metadata, $isRhyme);
            $audioScript = $this->taskTwoAAudioScript($row, $metadata, $wordOne, $wordTwo);

            $this->updateLearningContent(
                'rhyme_decision',
                $this->rowId($row),
                [
                    'title' => 'Rhyme decision '.$wordOne.' / '.$wordTwo,
                    'prompt' => $audioScript,
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'sequence' => $this->sequence($row, $metadata),
                        'assessment_type' => $row['assessment_type'] ?? $metadata['assessment_type'] ?? 'shared',
                        'task' => $row['task'] ?? $metadata['task'] ?? 'task_2a',
                        'item_type' => $row['item_type'] ?? $metadata['item_type'] ?? 'rhyme_decision',
                        'assessment_part' => $row['assessment_part'] ?? $metadata['assessment_part'] ?? 'task_2a',
                        'word_1' => $wordOne,
                        'word_2' => $wordTwo,
                        'is_rhyme' => $isRhyme,
                        'correct_answer' => $correctAnswer,
                        'prompt_text' => $row['prompt_text'] ?? $metadata['prompt_text'] ?? "Do {$wordOne} and {$wordTwo} rhyme?",
                        'audio_script' => $audioScript,
                        'vivian_prompt_script' => $row['vivian_script'] ?? $metadata['vivian_script'] ?? $metadata['vivian_prompt_script'] ?? $audioScript,
                        'points' => $this->points($row, $metadata),
                        'enrichment' => $enrichment,
                    ],
                    'accepted_answers' => [$correctAnswer],
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'] ?? $metadata['difficulty'] ?? 'easy',
                    'is_active' => $this->active($row['is_active'] ?? $row['active'] ?? true),
                ]
            );
        }
    }

    private function seedTaskTwoBWords(): void
    {
        foreach ($this->csv('task2b_word_in_sentence.csv') as $row) {
            $metadata = $this->metadata($row);
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));
            $this->updateLearningContent(
                'word_sentence',
                $this->rowId($row),
                [
                    'title' => 'Word sentence '.$this->rowId($row),
                    'prompt' => $row['sentence_text'] ?? $this->promptText($row, $metadata),
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'sequence' => $this->sequence($row, $metadata),
                        'target_word' => $row['target_word'] ?? $metadata['target_word'] ?? $this->expectedAnswer($row, $metadata),
                        'expected_answer' => $this->expectedAnswer($row, $metadata),
                        'points' => $this->points($row, $metadata),
                        'enrichment' => $enrichment,
                    ],
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedReadingPassages(): void
    {
        foreach ($this->csv('reading_passages.csv') as $row) {
            $metadata = $this->metadata($row);
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));
            $this->updateLearningContent(
                'reading_passage',
                $this->rowId($row),
                [
                    'title' => $row['title'] ?? $metadata['title'] ?? $this->rowId($row),
                    'prompt' => $row['passage_text'] ?? $this->promptText($row, $metadata),
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'word_count' => (int) ($row['word_count'] ?? $metadata['word_count'] ?? 0),
                        'expected_reading_time_seconds' => (int) ($row['expected_reading_time_seconds'] ?? $metadata['expected_reading_time_seconds'] ?? 0),
                        'max_time_seconds' => (int) ($row['max_time_seconds'] ?? $metadata['max_time_seconds'] ?? 0),
                        'story_number' => (int) ($row['story_number'] ?? $metadata['story_number'] ?? 0),
                        'assessment_active' => $this->active($row['assessment_active'] ?? $metadata['assessment_active'] ?? $row['is_active'] ?? true),
                        'enrichment' => $enrichment,
                    ],
                    'accepted_answers' => null,
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedComprehensionQuestions(): void
    {
        foreach ($this->csv('comprehension_questions.csv') as $row) {
            $metadata = $this->metadata($row);
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));
            $choices = [
                'A' => $row['choice_a'] ?? $metadata['choice_a'] ?? null,
                'B' => $row['choice_b'] ?? $metadata['choice_b'] ?? null,
                'C' => $row['choice_c'] ?? $metadata['choice_c'] ?? null,
                'D' => $row['choice_d'] ?? $metadata['choice_d'] ?? null,
            ];
            $correctChoice = strtoupper(trim((string) ($row['correct_choice'] ?? $metadata['correct_choice'] ?? '')));
            $correctAnswer = $choices[$correctChoice] ?? $row['correct_answer'] ?? $metadata['correct_answer'] ?? $this->expectedAnswer($row, $metadata);
            $questionType = $row['question_type'] ?? $metadata['question_type'] ?? 'multiple_choice';
            $acceptedAnswers = $questionType === 'multiple_choice'
                ? []
                : $this->pipeList($row['accepted_answers'] ?? '');

            if ($questionType !== 'multiple_choice' && $acceptedAnswers === [] && $correctAnswer !== null && trim((string) $correctAnswer) !== '') {
                $acceptedAnswers = [(string) $correctAnswer];
            }

            $this->updateLearningContent(
                'comprehension_question',
                $this->rowId($row),
                [
                    'title' => $this->rowId($row),
                    'prompt' => $row['question_text'] ?? $this->promptText($row, $metadata),
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'passage_id' => $row['passage_id'] ?? $metadata['passage_id'] ?? null,
                        'sequence' => $this->sequence($row, $metadata),
                        'question_type' => $questionType,
                        'correct_answer' => $correctAnswer,
                        'correct_choice' => $correctChoice,
                        'choices' => $choices,
                        'points' => $this->points($row, $metadata),
                        'enrichment' => $enrichment,
                    ],
                    'accepted_answers' => $acceptedAnswers,
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function csv(string $file): array
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.$file;
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function pipeList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }

    private function taskTwoATargetWord(array $row): string
    {
        $metadata = $this->metadata($row);

        return trim((string) ($row['target_word'] ?? $metadata['target_word'] ?? $row['expected_answer'] ?? $this->pipeList($row['accepted_answers'] ?? '')[0] ?? ''));
    }

    private function taskTwoAWordOne(array $row, array $metadata): string
    {
        return trim((string) ($row['word_1'] ?? $metadata['word_1'] ?? $row['prompt_text'] ?? $metadata['prompt_text'] ?? ''));
    }

    private function taskTwoAWordTwo(array $row, array $metadata): string
    {
        return trim((string) ($row['word_2'] ?? $metadata['word_2'] ?? $row['target_word'] ?? $metadata['target_word'] ?? ''));
    }

    private function taskTwoAIsRhyme(array $row, array $metadata): bool
    {
        return $this->active($row['is_rhyme'] ?? $metadata['is_rhyme'] ?? false);
    }

    private function taskTwoACorrectAnswer(array $row, array $metadata, bool $isRhyme): string
    {
        $answer = strtolower(trim((string) ($row['correct_answer'] ?? $metadata['correct_answer'] ?? ($isRhyme ? 'yes' : 'no'))));

        return $answer === 'yes' ? 'yes' : 'no';
    }

    private function taskTwoAAudioScript(array $row, array $metadata, string $wordOne, string $wordTwo): string
    {
        return trim((string) ($row['audio_script'] ?? $row['vivian_script'] ?? $metadata['audio_script'] ?? $metadata['vivian_script'] ?? "{$wordOne}, {$wordTwo}"));
    }

    private function active(string|int|bool|null $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private function metadata(array $row): array
    {
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function rowId(array $row): string
    {
        return (string) ($row['id'] ?? $row['prompt_id']);
    }

    private function promptText(array $row, array $metadata): string
    {
        return (string) ($row['prompt_text'] ?? $metadata['prompt_text'] ?? $row['expected_text'] ?? '');
    }

    private function expectedAnswer(array $row, array $metadata): ?string
    {
        return $row['expected_answer'] ?? $metadata['expected_answer'] ?? $row['expected_text'] ?? null;
    }

    private function sequence(array $row, array $metadata): int
    {
        return (int) ($row['sequence'] ?? $row['question_order'] ?? $row['order'] ?? $metadata['sequence'] ?? 0);
    }

    private function points(array $row, array $metadata): int
    {
        return (int) round((float) ($row['points'] ?? $metadata['points'] ?? 1));
    }

    private function updateLearningContent(string $contentType, string $sourceCsvId, array $attributes): LearningContent
    {
        $content = LearningContent::query()
            ->where('content_type', $contentType)
            ->where('payload->source_csv_id', $sourceCsvId)
            ->first();

        if (! $content) {
            $content = LearningContent::query()
                ->where('content_type', $contentType)
                ->where('title', $attributes['title'])
                ->first();
        }

        if ($content) {
            $content->fill($attributes);
            $content->save();

            return $content;
        }

        return LearningContent::create(['content_type' => $contentType] + $attributes);
    }

    private function enrichmentFor(string $promptId): array
    {
        $index = $this->enrichmentIndex();

        return $index[$promptId] ?? [];
    }

    private function rowEnrichment(array $row): array
    {
        $fields = [
            'expected_phonemes',
            'initial_phoneme',
            'vowel_phonemes',
            'final_phoneme',
            'phoneme_pattern',
            'skill_tag',
            'skill_group',
            'error_focus',
            'target_position',
            'target_phoneme',
            'difficulty_level',
            'difficulty_score',
            'adaptive_bucket',
            'recommended_for_error_type',
            'needs_manual_review',
        ];

        return collect($row)
            ->only($fields)
            ->all();
    }

    private function enrichmentIndex(): array
    {
        if ($this->enrichmentIndex !== null) {
            return $this->enrichmentIndex;
        }

        $path = $this->basePath.DIRECTORY_SEPARATOR.'enriched'.DIRECTORY_SEPARATOR.'enriched_content_index.csv';

        if (! is_file($path)) {
            return $this->enrichmentIndex = [];
        }

        $rows = $this->readCsvPath($path);
        $fields = [
            'expected_phonemes',
            'initial_phoneme',
            'vowel_phonemes',
            'final_phoneme',
            'phoneme_pattern',
            'skill_tag',
            'skill_group',
            'error_focus',
            'target_position',
            'target_phoneme',
            'difficulty_level',
            'difficulty_score',
            'adaptive_bucket',
            'recommended_for_error_type',
            'needs_manual_review',
        ];

        return $this->enrichmentIndex = collect($rows)
            ->filter(fn (array $row) => ($row['source_group'] ?? null) === 'assessment')
            ->mapWithKeys(fn (array $row) => [
                $row['prompt_id'] => collect($row)->only($fields)->filter(fn ($value) => $value !== '')->all(),
            ])
            ->all();
    }

    private function readCsvPath(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }
}
