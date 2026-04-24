<?php

namespace Database\Seeders;

use App\Models\LearningContent;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleContentSeeder extends Seeder
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = database_path('seed-data/readirect');
    }

    public function run(): void
    {
        $this->seedModuleActivities('module1_letter_sound_activities.csv');
        $this->seedModuleActivities('module2_word_reading_activities.csv');
        $this->seedModuleActivities('module3_sentence_fluency_activities.csv');
        $this->seedFeedbackTemplates();
        $this->seedSelectionRules();
    }

    private function seedModuleActivities(string $file): void
    {
        foreach ($this->csv($file) as $row) {
            $module = Module::where('key', $row['module_key'])->firstOrFail();
            $payload = [
                'source_csv_id' => $row['id'],
                'module_key' => $row['module_key'],
                'activity_type' => $row['activity_type'],
                'sequence' => (int) $row['sequence'],
                'expected_answer' => $row['expected_answer'],
                'target_word' => $row['target_word'] ?? null,
                'word_family' => $row['word_family'] ?? null,
                'points' => (int) $row['points'],
                'is_mastery_item' => $this->active($row['is_mastery_item']),
            ];

            $content = LearningContent::updateOrCreate(
                ['content_type' => 'module_activity', 'title' => $row['id']],
                [
                    'prompt' => $row['prompt_text'],
                    'payload' => $payload,
                    'accepted_answers' => $this->pipeList($row['accepted_answers']),
                    'difficulty' => $row['difficulty'],
                    'is_active' => $this->active($row['is_active']),
                ]
            );

            $module->activities()->updateOrCreate(
                ['learning_content_id' => $content->id],
                [
                    'sequence' => (int) $row['sequence'],
                    'activity_type' => $row['activity_type'],
                    'title' => $row['prompt_text'],
                    'configuration' => $payload,
                ]
            );
        }
    }

    private function seedFeedbackTemplates(): void
    {
        foreach ($this->csv('module_feedback_templates.csv') as $row) {
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
            LearningContent::updateOrCreate(
                ['content_type' => 'module_activity_selection_rule', 'title' => $row['id']],
                [
                    'prompt' => $row['activity_type'],
                    'payload' => [
                        'source_csv_id' => $row['id'],
                        'module_key' => $row['module_key'],
                        'activity_type' => $row['activity_type'],
                        'practice_item_count' => (int) $row['practice_item_count'],
                        'mastery_item_count' => (int) $row['mastery_item_count'],
                    ],
                    'accepted_answers' => null,
                    'difficulty' => 'grade_1',
                    'is_active' => $this->active($row['is_active']),
                ]
            );
        }
    }

    private function csv(string $file): array
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.$file;
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function pipeList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $value))));
    }

    private function active(string|int|null $value): bool
    {
        return (int) $value === 1;
    }
}
