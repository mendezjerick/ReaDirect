<?php

namespace App\Services;

use App\Models\Module;

class ModuleExperienceService
{
    public function overview(Module $module, array $activityTypes): array
    {
        return [
            'purpose' => $this->purpose($module->key),
            'guide_message' => $this->guideMessage($module->key),
            'lesson_boxes' => $this->lessonBoxes($module->key, $activityTypes),
            'goodbye_message' => 'See you next time. Keep practicing, keep using your clear voice, and remember that every step helps.',
        ];
    }

    public function masteryMessage(string $decisionKey): string
    {
        return match ($decisionKey) {
            'move_to_module_2' => 'Great work! You are ready for Module 2, where we will practice reading words one careful step at a time.',
            'repeat_module_1' => 'You are doing better. Let us practice Module 1 again so your letter sounds can feel stronger.',
            'move_to_module_3' => 'Great job! You are ready for Module 3, where we will practice reading sentences smoothly.',
            'repeat_module_2' => 'Let us practice these words again so you can feel more confident and steady when you read them.',
            'return_to_module_1' => 'We will go back to letter sounds for more practice. This will help your word reading become stronger.',
            'proceed_to_reassessment' => 'You worked hard in your modules. Stay calm, listen carefully, and do your best on your final reading check.',
            'repeat_module_3' => 'Let us practice sentence reading again so you can read more smoothly and clearly.',
            'return_to_module_2' => 'We will practice words again to help your sentence reading become stronger and easier.',
            default => 'Great effort! Your next reading step is ready, and we will keep moving one step at a time.',
        };
    }

    private function purpose(string $moduleKey): string
    {
        return match ($moduleKey) {
            'module_1' => 'You will practice letters and sounds so you can say them clearly.',
            'module_2' => 'You will practice reading words and understanding how they sound.',
            'module_3' => 'You will practice reading sentences smoothly and clearly.',
            default => 'You will practice reading one step at a time.',
        };
    }

    private function guideMessage(string $moduleKey): string
    {
        return match ($moduleKey) {
            'module_1' => 'Hi, I am Miss Ciel. We will practice letter sounds together, and you can choose a lesson box when you are ready.',
            'module_2' => 'Hi, I am Miss Ciel. We will practice reading words clearly, one careful sound at a time.',
            'module_3' => 'Hi, I am Miss Ciel. We will practice smooth sentence reading, and we will go slowly together.',
            default => 'Hi, I am Miss Ciel. I will guide your practice one step at a time, and we can go slowly together.',
        };
    }

    private function lessonBoxes(string $moduleKey, array $activityTypes): array
    {
        return collect($activityTypes)
            ->reject(fn (string $type): bool => $type === 'mastery_check')
            ->map(fn (string $type): array => [
                'key' => $type,
                'title' => $this->activityTitle($moduleKey, $type),
                'description' => $this->activityDescription($moduleKey, $type),
                'explanation' => $this->activityExplanation($moduleKey, $type),
            ])
            ->values()
            ->all();
    }

    private function activityTitle(string $moduleKey, string $activityType): string
    {
        $fallback = str($activityType)->replace('_', ' ')->title()->toString();

        if (($title = $this->moduleActivityTitle($moduleKey, $activityType)) !== null) {
            return $title;
        }

        return match (true) {
            $activityType === 'letter_pair_identification' => 'Display Letter Pair',
            $activityType === 'highlighted_first_letter' => 'Highlighted First Letter',
            $activityType === 'first_letter_identification' => 'First Letter',
            $activityType === 'missing_first_letter' => 'Missing First Letter',
            $activityType === 'display_word_reading' => 'Display Word',
            $activityType === 'split_word_reading' => 'Split Word',
            $activityType === 'highlighted_rhyme_word' => 'Highlighted Rhyme Word',
            $activityType === 'highlighted_sentence_word' => 'Highlighted Sentence Word',
            $activityType === 'simple_sentence_reading' => 'Simple Sentence',
            $activityType === 'comma_pause_reading' => 'Comma Pause',
            $activityType === 'full_stop_pause_reading' => 'Full-Stop Pause',
            $activityType === 'mixed_punctuation_fluency' => 'Mixed Punctuation',
            default => $fallback,
        };
    }

    private function activityDescription(string $moduleKey, string $activityType): string
    {
        if (($description = $this->moduleActivityDescription($moduleKey, $activityType)) !== null) {
            return $description;
        }

        return match ($activityType) {
            'letter_pair_identification' => 'Look at a letter pair and say the letter name.',
            'highlighted_first_letter' => 'Look at a word and say the highlighted first letter.',
            'first_letter_identification' => 'Find the first letter of a word without a highlight.',
            'missing_first_letter' => 'Compare a full word with a missing-letter word.',
            'display_word_reading' => 'Read one displayed word clearly.',
            'split_word_reading' => 'Blend word parts and read the whole word.',
            'highlighted_rhyme_word' => 'Read only the highlighted word in a rhyming group.',
            'highlighted_sentence_word' => 'Read only the highlighted word in a sentence.',
            'simple_sentence_reading' => 'Read one simple sentence from start to finish.',
            'comma_pause_reading' => 'Read a sentence and make a small comma pause.',
            'full_stop_pause_reading' => 'Read two sentences with a stronger full-stop pause.',
            'mixed_punctuation_fluency' => 'Read mixed punctuation smoothly and clearly.',
            default => 'Practice this reading step.',
        };
    }

    private function activityExplanation(string $moduleKey, string $activityType): string
    {
        if (($explanation = $this->moduleActivityExplanation($moduleKey, $activityType)) !== null) {
            return $explanation;
        }

        return match ($activityType) {
            'letter_pair_identification' => 'This lesson shows an uppercase and lowercase letter together. Say the letter name clearly.',
            'highlighted_first_letter' => 'This lesson highlights the first letter in a word. Say that starting letter clearly.',
            'first_letter_identification' => 'This lesson removes the highlight so you can find and say the first letter yourself.',
            'missing_first_letter' => 'This lesson shows a full word and a word missing its first letter. Say the letter that completes it.',
            'display_word_reading' => 'This lesson helps you read one short word clearly from the letters on the screen.',
            'split_word_reading' => 'This lesson splits the word into parts so you can blend them and read the whole word.',
            'highlighted_rhyme_word' => 'This lesson shows rhyming words together. Read only the highlighted target word.',
            'highlighted_sentence_word' => 'This lesson places the target word in a sentence. Read only the highlighted word.',
            'simple_sentence_reading' => 'This lesson helps you read one complete sentence accurately from start to finish.',
            'comma_pause_reading' => 'This lesson helps you keep reading smoothly while making a small pause at the comma.',
            'full_stop_pause_reading' => 'This lesson helps you pause more strongly at the end of one sentence before starting the next.',
            'mixed_punctuation_fluency' => 'This lesson combines accuracy, comma pauses, and full-stop pauses in one reading.',
            default => 'This part helps you practice reading in a clear way.',
        };
    }

    private function moduleActivityTitle(string $moduleKey, string $activityType): ?string
    {
        return match ($moduleKey) {
            'module_1' => $this->moduleOneActivityTitle($activityType),
            'module_2' => $this->moduleTwoActivityTitle($activityType),
            'module_3' => $this->moduleThreeActivityTitle($activityType),
            default => null,
        };
    }

    private function moduleActivityDescription(string $moduleKey, string $activityType): ?string
    {
        return match ($moduleKey) {
            'module_1' => $this->moduleOneActivityDescription($activityType),
            'module_2' => $this->moduleTwoActivityDescription($activityType),
            'module_3' => $this->moduleThreeActivityDescription($activityType),
            default => null,
        };
    }

    private function moduleActivityExplanation(string $moduleKey, string $activityType): ?string
    {
        return match ($moduleKey) {
            'module_1' => $this->moduleOneActivityExplanation($activityType),
            'module_2' => $this->moduleTwoActivityExplanation($activityType),
            'module_3' => $this->moduleThreeActivityExplanation($activityType),
            default => null,
        };
    }

    private function moduleOneActivityTitle(string $activityType): ?string
    {
        return match ($activityType) {
            'letter_pair_identification' => 'Display Letter Pair',
            'highlighted_first_letter' => 'Highlighted First Letter',
            'first_letter_identification' => 'First Letter',
            'missing_first_letter' => 'Missing First Letter',
            default => null,
        };
    }

    private function moduleOneActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'letter_pair_identification' => 'Say the letter shown as an uppercase and lowercase pair.',
            'highlighted_first_letter' => 'Say the highlighted first letter in the word.',
            'first_letter_identification' => 'Find and say the first letter in the word.',
            'missing_first_letter' => 'Say the missing first letter that completes the word.',
            default => null,
        };
    }

    private function moduleOneActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'letter_pair_identification' => 'This box shows a letter pair like Aa. Say the letter name clearly.',
            'highlighted_first_letter' => 'This box shows a word with the first letter highlighted. Say the highlighted letter.',
            'first_letter_identification' => 'This box shows a word without a highlight. Find the first letter and say it.',
            'missing_first_letter' => 'This box shows a full word beside a missing-letter word. Say the missing first letter.',
            default => null,
        };
    }

    private function moduleTwoActivityTitle(string $activityType): ?string
    {
        return match ($activityType) {
            'display_word_reading' => 'Display Word',
            'split_word_reading' => 'Split Word',
            'highlighted_rhyme_word' => 'Highlighted Rhyme Word',
            'highlighted_sentence_word' => 'Highlighted Sentence Word',
            default => null,
        };
    }

    private function moduleTwoActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'display_word_reading' => 'Read one short word clearly.',
            'split_word_reading' => 'Blend the parts and read the whole word.',
            'highlighted_rhyme_word' => 'Read only the highlighted word in a rhyme group.',
            'highlighted_sentence_word' => 'Read only the highlighted word in a sentence.',
            default => null,
        };
    }

    private function moduleTwoActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'display_word_reading' => 'This box is for reading one short word at a time. Look at the word, then say it clearly.',
            'split_word_reading' => 'This box splits a word into beginning and ending parts. Blend the parts and read the whole word.',
            'highlighted_rhyme_word' => 'This box shows rhyming words together. Read only the highlighted word.',
            'highlighted_sentence_word' => 'This box shows a sentence with one highlighted word. Read only that word.',
            default => null,
        };
    }

    private function moduleThreeActivityTitle(string $activityType): ?string
    {
        return match ($activityType) {
            'simple_sentence_reading' => 'Simple Sentence',
            'comma_pause_reading' => 'Comma Pause',
            'full_stop_pause_reading' => 'Full-Stop Pause',
            'mixed_punctuation_fluency' => 'Mixed Punctuation Fluency',
            default => null,
        };
    }

    private function moduleThreeActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'simple_sentence_reading' => 'Read one full sentence clearly.',
            'comma_pause_reading' => 'Read with a small pause at the comma.',
            'full_stop_pause_reading' => 'Read two sentences with a full-stop pause between them.',
            'mixed_punctuation_fluency' => 'Read smoothly with comma and full-stop pauses.',
            default => null,
        };
    }

    private function moduleThreeActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'simple_sentence_reading' => 'This box is for reading a full sentence accurately from start to finish.',
            'comma_pause_reading' => 'This box helps you read a sentence and pause just a little when you see a comma.',
            'full_stop_pause_reading' => 'This box helps you pause more strongly after one sentence before reading the next one.',
            'mixed_punctuation_fluency' => 'This box combines accurate sentence reading with smooth comma and full-stop pauses.',
            default => null,
        };
    }
}
