<?php

namespace Database\Seeders;

use App\Models\LearningContent;
use App\Models\Module;
use App\Models\ModuleActivity;
use Illuminate\Database\Seeder;

class ModuleContentSeeder extends Seeder
{
    private string $basePath;

    private ?array $enrichmentIndex = null;

    private array $seededModuleActivitySourceIds = [];

    private array $seededSelectionRuleSourceIds = [];

    private array $seededFeedbackTemplateSourceIds = [];

    public function __construct()
    {
        $this->basePath = database_path('seed-data/readirect');
    }

    public function run(): void
    {
        $this->seedModuleActivities('module1_letter_sound_activities_adaptive_v2.csv');
        $this->seedModuleActivities('module2_word_reading_activities_adaptive_v2.csv');
        $this->seedModuleActivities('module3_sentence_fluency_activities_adaptive_v2.csv');
        $this->seedFeedbackTemplates();
        $this->seedSelectionRules();
        $this->deactivateStaleSeededContent();
    }

    private function seedModuleActivities(string $file): void
    {
        foreach ($this->csv($file) as $row) {
            $this->seededModuleActivitySourceIds[] = $this->rowId($row);
            $metadata = $this->metadata($row);
            $module = Module::where('key', $row['module_key'])->firstOrFail();
            $payload = array_merge([
                'source_csv_id' => $this->rowId($row),
                'module_key' => $row['module_key'],
                'activity_type' => $row['activity_type'],
                'sequence' => $this->sequence($row, $metadata),
                'display_text' => $row['display_text'] ?? $metadata['display_text'] ?? null,
                'expected_answer' => $this->expectedAnswer($row, $metadata),
                'target_word' => $row['target_word'] ?? $metadata['target_word'] ?? $this->expectedAnswer($row, $metadata),
                'target_sentence' => $row['target_sentence'] ?? $metadata['target_sentence'] ?? null,
                'word_family' => $row['word_family'] ?? null,
                'points' => $this->points($row, $metadata),
                'is_mastery_item' => $this->active($row['is_mastery_item']),
            ], $this->displayPayload($row, $metadata), $this->fluencyPayload($row, $metadata));
            $enrichment = $this->rowEnrichment($row) + $this->enrichmentFor($this->rowId($row));

            $content = $this->updateLearningContent(
                'module_activity',
                $this->rowId($row),
                [
                    'title' => $this->rowId($row),
                    'prompt' => $row['prompt_text'],
                    'payload' => array_merge($payload, ['enrichment' => $enrichment]),
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'enrichment_metadata' => $enrichment,
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );

            ModuleActivity::updateOrCreate(
                ['learning_content_id' => $content->id],
                [
                    'module_id' => $module->id,
                    'sequence' => $payload['sequence'],
                    'activity_type' => $row['activity_type'],
                    'title' => $row['prompt_text'],
                    'configuration' => array_merge($payload, ['enrichment' => $enrichment]),
                ]
            );
        }
    }

    private function seedFeedbackTemplates(): void
    {
        foreach ($this->csv('module_feedback_templates.csv') as $row) {
            $this->seededFeedbackTemplateSourceIds[] = $this->rowId($row);
            LearningContent::updateOrCreate(
                ['content_type' => 'module_feedback_template', 'title' => $row['id']],
                [
                    'prompt' => $row['feedback_text'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'module_key' => $row['module_key'],
                        'activity_type' => $row['activity_type'],
                        'error_type' => $row['error_type'],
                        'severity' => $row['severity'],
                        'retry_instruction' => $row['retry_instruction'],
                        'success_text' => $row['success_text'],
                    ],
                    'accepted_answers' => null,
                    'difficulty' => 'grade_1',
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function seedSelectionRules(): void
    {
        foreach ($this->csv('module_activity_selection_rules.csv') as $row) {
            $this->seededSelectionRuleSourceIds[] = $this->rowId($row);
            $metadata = $this->metadata($row);
            $this->updateLearningContent(
                'module_activity_selection_rule',
                $this->rowId($row),
                [
                    'title' => $this->rowId($row),
                    'prompt' => $row['activity_type'],
                    'payload' => [
                        'source_csv_id' => $this->rowId($row),
                        'module_key' => $row['module_key'],
                        'activity_type' => $row['activity_type'],
                        'practice_item_count' => (int) ($row['practice_item_count'] ?? $metadata['practice_item_count'] ?? 0),
                        'mastery_item_count' => (int) ($row['mastery_item_count'] ?? $metadata['mastery_item_count'] ?? 0),
                    ],
                    'accepted_answers' => null,
                    'difficulty' => 'grade_1',
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function deactivateStaleSeededContent(): void
    {
        $this->deactivateStaleContent(
            'module_activity',
            $this->seededModuleActivitySourceIds,
            fn (array $payload): bool => in_array((string) ($payload['module_key'] ?? ''), ['module_1', 'module_2', 'module_3', 'advanced_module'], true)
        );

        $this->deactivateStaleContent(
            'module_activity_selection_rule',
            $this->seededSelectionRuleSourceIds,
            fn (array $payload): bool => in_array((string) ($payload['module_key'] ?? ''), ['module_1', 'module_2', 'module_3', 'advanced_module'], true)
        );

        $this->deactivateStaleContent(
            'module_feedback_template',
            $this->seededFeedbackTemplateSourceIds,
            fn (array $payload): bool => array_key_exists('module_key', $payload)
        );
    }

    private function deactivateStaleContent(string $contentType, array $currentSourceIds, callable $belongsToThisSeeder): void
    {
        $currentSourceIds = array_flip(array_unique($currentSourceIds));

        LearningContent::query()
            ->where('content_type', $contentType)
            ->where('is_active', true)
            ->get()
            ->each(function (LearningContent $content) use ($currentSourceIds, $belongsToThisSeeder): void {
                $payload = $content->payload ?? [];

                if (! $belongsToThisSeeder($payload)) {
                    return;
                }

                $sourceCsvId = (string) ($payload['source_csv_id'] ?? '');
                if ($sourceCsvId === '' || isset($currentSourceIds[$sourceCsvId])) {
                    return;
                }

                $content->update(['is_active' => false]);
            });
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

    private function expectedAnswer(array $row, array $metadata): ?string
    {
        return $row['expected_answer'] ?? $metadata['expected_answer'] ?? $row['expected_text'] ?? null;
    }

    private function sequence(array $row, array $metadata): int
    {
        return (int) ($row['sequence'] ?? $metadata['sequence'] ?? 0);
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
            'display_text',
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
            'fluency_metric_rule',
            'target_read_time_seconds',
            'min_fluent_time_seconds',
            'max_fluent_time_seconds',
            'target_wcpm',
            'min_expected_wcpm',
            'max_expected_wcpm',
            'pace_feedback_rule',
            'pace_mastery_required',
        ];

        return collect($row)
            ->only($fields)
            ->all();
    }

    private function fluencyPayload(array $row, array $metadata): array
    {
        return [
            'fluency_metric_rule' => $this->nullableText($row['fluency_metric_rule'] ?? $metadata['fluency_metric_rule'] ?? null),
            'target_read_time_seconds' => $this->nullableFloat($row['target_read_time_seconds'] ?? $metadata['target_read_time_seconds'] ?? null),
            'min_fluent_time_seconds' => $this->nullableFloat($row['min_fluent_time_seconds'] ?? $metadata['min_fluent_time_seconds'] ?? null),
            'max_fluent_time_seconds' => $this->nullableFloat($row['max_fluent_time_seconds'] ?? $metadata['max_fluent_time_seconds'] ?? null),
            'target_wcpm' => $this->nullableFloat($row['target_wcpm'] ?? $metadata['target_wcpm'] ?? null),
            'min_expected_wcpm' => $this->nullableFloat($row['min_expected_wcpm'] ?? $metadata['min_expected_wcpm'] ?? null),
            'max_expected_wcpm' => $this->nullableFloat($row['max_expected_wcpm'] ?? $metadata['max_expected_wcpm'] ?? null),
            'pace_feedback_rule' => $this->nullableText($row['pace_feedback_rule'] ?? $metadata['pace_feedback_rule'] ?? null),
            'pace_mastery_required' => $this->nullableBool($row['pace_mastery_required'] ?? $metadata['pace_mastery_required'] ?? null),
        ];
    }

    private function displayPayload(array $row, array $metadata): array
    {
        $fields = [
            'canonical_target',
            'target_letter',
            'target_word',
            'target_sentence',
            'highlight_target',
            'highlighted_letter',
            'highlighted_word',
            'target_word_index',
            'split_word_display',
            'rhyme_group',
            'sentence_with_target',
            'punctuation_focus',
            'display_format',
        ];

        return collect($fields)
            ->mapWithKeys(function (string $field) use ($row, $metadata): array {
                $value = $row[$field] ?? $metadata[$field] ?? null;

                if ($field === 'highlight_target') {
                    return [$field => $this->nullableBool($value)];
                }

                if ($field === 'target_word_index') {
                    return [$field => is_numeric($value) ? (int) $value : null];
                }

                if ($field === 'rhyme_group' && is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return [$field => $decoded];
                    }
                }

                return [$field => $value === '' ? null : $value];
            })
            ->filter(fn (mixed $value): bool => $value !== null)
            ->all();
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
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
            ->filter(fn (array $row) => ($row['source_group'] ?? null) === 'modules')
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
