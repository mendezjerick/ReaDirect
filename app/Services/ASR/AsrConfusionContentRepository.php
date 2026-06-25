<?php

namespace App\Services\ASR;

use Illuminate\Support\Str;

class AsrConfusionContentRepository
{
    private const SOURCES = [
        [
            'category' => 'diagnostic',
            'task' => 'task_1a',
            'label' => 'Diagnostic Task 1A',
            'file' => 'task1_letter_pronunciation.csv',
            'prompt_type' => 'letter',
            'task_type' => 'crla_task_1_letter',
            'activity_type' => 'letter',
            'assessment_type' => 'diagnostic',
        ],
        [
            'category' => 'diagnostic',
            'task' => 'task_2b',
            'label' => 'Diagnostic Task 2B',
            'file' => 'task2b_word_in_sentence.csv',
            'prompt_type' => 'word',
            'task_type' => 'crla_task_2b_sentence',
            'activity_type' => 'word_sentence',
            'assessment_type' => 'diagnostic',
        ],
        [
            'category' => 'diagnostic',
            'task' => 'passage_reading',
            'label' => 'Diagnostic Passage Reading',
            'file' => 'reading_passages.csv',
            'prompt_type' => 'reading_passage',
            'task_type' => 'reading_passage',
            'activity_type' => 'passage_reading',
            'assessment_type' => 'diagnostic',
        ],
        [
            'category' => 'final',
            'task' => 'task_1a',
            'label' => 'Final Task 1A',
            'file' => 'task1_letter_pronunciation.csv',
            'prompt_type' => 'letter',
            'task_type' => 'crla_task_1_letter',
            'activity_type' => 'letter',
            'assessment_type' => 'final_reassessment',
        ],
        [
            'category' => 'final',
            'task' => 'task_2b',
            'label' => 'Final Task 2B',
            'file' => 'task2b_word_in_sentence.csv',
            'prompt_type' => 'word',
            'task_type' => 'crla_task_2b_sentence',
            'activity_type' => 'word_sentence',
            'assessment_type' => 'final_reassessment',
        ],
        [
            'category' => 'final',
            'task' => 'passage_reading',
            'label' => 'Final Passage Reading',
            'file' => 'reading_passages.csv',
            'prompt_type' => 'reading_passage',
            'task_type' => 'final_reading_passage',
            'activity_type' => 'passage_reading',
            'assessment_type' => 'final_reassessment',
        ],
        [
            'category' => 'modules',
            'task' => 'module_1',
            'label' => 'Module 1',
            'file' => 'module1_letter_sound_activities.csv',
            'prompt_type' => 'letter',
            'assessment_type' => 'module_activity',
        ],
        [
            'category' => 'modules',
            'task' => 'module_2',
            'label' => 'Module 2',
            'file' => 'module2_word_reading_activities.csv',
            'prompt_type' => 'word',
            'assessment_type' => 'module_activity',
        ],
        [
            'category' => 'modules',
            'task' => 'module_3',
            'label' => 'Module 3',
            'file' => 'module3_sentence_fluency_activities.csv',
            'prompt_type' => 'sentence',
            'assessment_type' => 'module_activity',
        ],
    ];

    public function items(?string $category = null): array
    {
        $category = $this->normalizeCategory($category);
        $items = [];

        foreach (self::SOURCES as $source) {
            if ($category !== null && $source['category'] !== $category) {
                continue;
            }

            foreach ($this->csv($source['file']) as $row) {
                if (! $this->active($row['is_active'] ?? null)) {
                    continue;
                }

                $expected = $this->expectedText($row, $source);

                if ($expected === '') {
                    continue;
                }

                $itemKey = $this->itemKey($row);
                $moduleKey = trim((string) ($row['module_key'] ?? ''));
                $activityType = trim((string) ($source['activity_type'] ?? $row['activity_type'] ?? $row['task_type'] ?? ''));
                $taskType = trim((string) ($source['task_type'] ?? $row['task_type'] ?? $activityType));
                $promptType = $source['prompt_type'] ?? $this->inferPromptType($expected, $row, $activityType);

                $items[] = [
                    'category' => $source['category'],
                    'category_label' => ucfirst($source['category']),
                    'task' => $source['task'],
                    'task_label' => $source['label'],
                    'item_key' => $itemKey,
                    'item_slug' => $this->slug($itemKey),
                    'source_file' => $source['file'],
                    'source_group' => $row['source_group'] ?? ($source['category'] === 'modules' ? 'modules' : 'assessment'),
                    'prompt_text' => trim((string) ($row['prompt_text'] ?? $row['passage_text'] ?? $expected)),
                    'expected_answer' => $expected,
                    'accepted_answers' => $this->pipeList($row['accepted_answers'] ?? ''),
                    'prompt_type' => $promptType,
                    'task_type' => $taskType,
                    'activity_type' => $activityType,
                    'assessment_type' => $source['assessment_type'],
                    'module_key' => $moduleKey !== '' ? $moduleKey : null,
                    'difficulty' => trim((string) ($row['difficulty'] ?? '')),
                    'sequence' => (int) ($row['sequence'] ?? data_get($this->metadata($row), 'sequence', 0)),
                    'metadata' => [
                        'row' => $this->compactRow($row),
                        'seed_file' => database_path('seed-data/readirect/'.$source['file']),
                    ],
                ];
            }
        }

        return $items;
    }

    public function availableCategories(): array
    {
        return ['diagnostic', 'final', 'modules'];
    }

    private function csv(string $file): array
    {
        $path = database_path('seed-data/readirect/'.$file);

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function expectedText(array $row, array $source): string
    {
        $metadata = $this->metadata($row);

        if (($source['task'] ?? '') === 'passage_reading') {
            return trim((string) ($row['passage_text'] ?? $row['expected_text'] ?? $row['prompt_text'] ?? $metadata['passage_text'] ?? ''));
        }

        return trim((string) (
            $row['expected_text']
            ?? $row['expected_answer']
            ?? $row['target_word']
            ?? $metadata['expected_answer']
            ?? $metadata['target_word']
            ?? $row['prompt_text']
            ?? ''
        ));
    }

    private function inferPromptType(string $expected, array $row, ?string $activityType): string
    {
        $haystack = Str::lower(implode(' ', [
            $row['task_type'] ?? '',
            $row['activity_type'] ?? '',
            $activityType ?? '',
            $row['item_text_type'] ?? '',
            $row['skill_group'] ?? '',
        ]));

        if (str_contains($haystack, 'passage')) {
            return 'reading_passage';
        }

        if (str_contains($haystack, 'sentence')) {
            return 'sentence';
        }

        if (str_contains($haystack, 'letter') || mb_strlen($expected) === 1) {
            return 'letter';
        }

        return str_contains($expected, ' ') ? 'sentence' : 'word';
    }

    private function itemKey(array $row): string
    {
        return trim((string) ($row['prompt_id'] ?? $row['id'] ?? uniqid('item_', false)));
    }

    private function slug(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString() ?: 'item';
    }

    private function pipeList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }

    private function active(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private function metadata(array $row): array
    {
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);

        return is_array($metadata) ? $metadata : [];
    }

    private function compactRow(array $row): array
    {
        return collect($row)
            ->only([
                'prompt_id',
                'source_file',
                'source_group',
                'module_key',
                'task_type',
                'activity_type',
                'prompt_text',
                'expected_text',
                'accepted_answers',
                'difficulty',
                'points',
                'is_active',
                'is_mastery_item',
                'item_text_type',
                'skill_group',
                'error_focus',
                'target_position',
                'target_phoneme',
                'word_count',
                'sentence_length_bucket',
                'title',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function normalizeCategory(?string $category): ?string
    {
        $category = trim((string) $category);

        if ($category === '') {
            return null;
        }

        return $category === 'module' ? 'modules' : $category;
    }
}
