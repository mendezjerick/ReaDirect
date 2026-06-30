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
            ['sequence' => 1, 'key' => 'module_1', 'title' => 'Letter and Sound Learning', 'description' => 'Letter sound practice, hear and repeat, matching, drills, and mini mastery check.'],
            ['sequence' => 2, 'key' => 'module_2', 'title' => 'Word Reading', 'description' => 'Word families, minimal pairs, word accuracy challenge, and mini mastery check.'],
            ['sequence' => 3, 'key' => 'module_3', 'title' => 'Sentence Reading and Fluency', 'description' => 'Sentence reading, guided practice, timed practice, pause practice, and mini mastery check.'],
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
                ['type' => 'letter_sound_practice', 'title' => 'Letter sound practice'],
                ['type' => 'hear_and_repeat', 'title' => 'Hear and repeat'],
                ['type' => 'sound_to_letter', 'title' => 'Match sound to letter'],
                ['type' => 'sound_drill', 'title' => 'Sound drills'],
                ['type' => 'mastery_check', 'title' => 'Mini mastery check'],
            ],
            'module_2' => [
                ['type' => 'read_word', 'title' => 'Read the word'],
                ['type' => 'word_family_drill', 'title' => 'Word family drill'],
                ['type' => 'minimal_pair', 'title' => 'Minimal pair practice'],
                ['type' => 'word_accuracy', 'title' => 'Word accuracy challenge'],
                ['type' => 'mastery_check', 'title' => 'Mini mastery check'],
            ],
            default => [
                ['type' => 'read_sentence', 'title' => 'Read the sentence'],
                ['type' => 'coach_reading', 'title' => 'Read with the coach'],
                ['type' => 'timed_sentence', 'title' => 'Timed sentence reading'],
                ['type' => 'pause_practice', 'title' => 'Pause practice'],
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
