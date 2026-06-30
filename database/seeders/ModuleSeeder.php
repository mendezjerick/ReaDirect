<?php

namespace Database\Seeders;

use App\Models\MasteryThreshold;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['sequence' => 1, 'key' => 'module_1', 'title' => 'Letter and Sound Learning', 'description' => 'Letter names, first-letter recognition, missing-letter practice, and mini mastery check.'],
            ['sequence' => 2, 'key' => 'module_2', 'title' => 'Word Reading', 'description' => 'Whole words, split words, highlighted rhyme words, highlighted sentence words, and mini mastery check.'],
            ['sequence' => 3, 'key' => 'module_3', 'title' => 'Sentence Reading and Fluency', 'description' => 'Simple sentences, comma pauses, full-stop pauses, mixed punctuation fluency, and mini mastery check.'],
        ];

        foreach ($modules as $moduleData) {
            $module = Module::updateOrCreate(['key' => $moduleData['key']], $moduleData + ['is_active' => true]);

            foreach ($this->activitiesFor($module->key) as $sequence => $activity) {
                $module->activities()->updateOrCreate(
                    ['sequence' => $sequence + 1],
                    ['activity_type' => $activity['type'], 'title' => $activity['title'], 'configuration' => $activity['configuration'] ?? []]
                );
            }
        }

        $this->seedThresholds();
    }

    private function activitiesFor(string $moduleKey): array
    {
        return match ($moduleKey) {
            'module_1' => [
                ['type' => 'letter_pair_identification', 'title' => 'Display letter pair'],
                ['type' => 'highlighted_first_letter', 'title' => 'Highlighted first letter'],
                ['type' => 'first_letter_identification', 'title' => 'First letter'],
                ['type' => 'missing_first_letter', 'title' => 'Missing first letter'],
                ['type' => 'mastery_check', 'title' => 'Mini mastery check'],
            ],
            'module_2' => [
                ['type' => 'display_word_reading', 'title' => 'Display word'],
                ['type' => 'split_word_reading', 'title' => 'Split word'],
                ['type' => 'highlighted_rhyme_word', 'title' => 'Highlighted rhyme word'],
                ['type' => 'highlighted_sentence_word', 'title' => 'Highlighted sentence word'],
                ['type' => 'mastery_check', 'title' => 'Mini mastery check'],
            ],
            default => [
                ['type' => 'simple_sentence_reading', 'title' => 'Simple sentence'],
                ['type' => 'comma_pause_reading', 'title' => 'Comma pause'],
                ['type' => 'full_stop_pause_reading', 'title' => 'Full-stop pause'],
                ['type' => 'mixed_punctuation_fluency', 'title' => 'Mixed punctuation fluency'],
                ['type' => 'mastery_check', 'title' => 'Mini mastery check'],
            ],
        };
    }

    private function seedThresholds(): void
    {
        $rules = [
            'module_1' => [
                [90, null, 'move_to_module_2', 'module_2', 'MODULE_1_MASTERY_V1'],
                [0, 89, 'repeat_module_1', 'module_1', 'MODULE_1_MASTERY_V1'],
            ],
            'module_2' => [
                [90, null, 'move_to_module_3', 'module_3', 'MODULE_2_MASTERY_V1'],
                [60, 89, 'repeat_module_2', 'module_2', 'MODULE_2_MASTERY_V1'],
                [0, 59, 'return_to_module_1', 'module_1', 'MODULE_2_MASTERY_V1'],
            ],
            'module_3' => [
                [90, null, 'proceed_to_reassessment', null, 'MODULE_3_MASTERY_V1'],
                [70, 89, 'repeat_module_3', 'module_3', 'MODULE_3_MASTERY_V1'],
                [0, 69, 'return_to_module_2', 'module_2', 'MODULE_3_MASTERY_V1'],
            ],
        ];

        foreach ($rules as $moduleKey => $thresholds) {
            $module = Module::where('key', $moduleKey)->firstOrFail();

            if ($moduleKey === 'module_1') {
                MasteryThreshold::where('module_id', $module->id)
                    ->where('decision', 'extra_phoneme_drills')
                    ->delete();
            }

            foreach ($thresholds as [$min, $max, $decision, $nextModuleKey, $ruleKey]) {
                MasteryThreshold::updateOrCreate(
                    ['module_id' => $module->id, 'decision' => $decision],
                    ['min_score' => $min, 'max_score' => $max, 'next_module_key' => $nextModuleKey, 'rule_key' => $ruleKey]
                );
            }
        }
    }

}
