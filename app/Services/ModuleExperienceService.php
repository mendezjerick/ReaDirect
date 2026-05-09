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
            'goodbye_message' => 'See you next time!',
        ];
    }

    public function masteryMessage(string $decisionKey): string
    {
        return match ($decisionKey) {
            'move_to_module_2' => 'Great work! You are ready for Module 2.',
            'repeat_module_1' => 'You are doing better. Let us practice Module 1 again to make your sounds stronger.',
            'extra_phoneme_drills' => 'Let us practice some sounds first. These drills will help you before trying again.',
            'move_to_module_3' => 'Great job! You are ready for Module 3.',
            'repeat_module_2' => 'Let us practice these words again so you can feel more confident.',
            'return_to_module_1' => 'We will go back to letter sounds for more practice. This will help your word reading.',
            'proceed_to_reassessment' => 'You worked hard in your modules. Do your best on your final reading check!',
            'repeat_module_3' => 'Let us practice sentence reading again so you can read more smoothly.',
            'return_to_module_2' => 'We will practice words again to help your sentence reading become stronger.',
            default => 'Great effort! Your next reading step is ready.',
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
            'module_1' => 'Hi! I am Miss Ciel. We will practice letter sounds together. Choose a lesson box to hear what it means.',
            'module_2' => 'Hi! I am Miss Ciel. We will practice reading words clearly. Choose a lesson box to hear what it means.',
            'module_3' => 'Hi! I am Miss Ciel. We will practice smooth sentence reading. Choose a lesson box to hear what it means.',
            default => 'Hi! I am Miss Ciel. I will guide your practice one step at a time.',
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

        return match (true) {
            $activityType === 'hear_and_repeat' => 'Hear and Repeat',
            $activityType === 'listen_and_say' => 'Listen and Say',
            $activityType === 'letter_sounds' => 'Letter Sounds',
            $activityType === 'see_letter_say_sound' => 'See the Letter',
            $activityType === 'match_sound_to_letter' => 'Match the Sound',
            $activityType === 'sound_drill' => 'Sound Drill',
            $activityType === 'read_word' => 'Read the Word',
            $activityType === 'word_family_drill' => 'Word Families',
            $activityType === 'minimal_pair' => 'Sound-Alike Words',
            $activityType === 'word_accuracy_challenge' => 'Word Accuracy',
            $activityType === 'read_sentence' => 'Read the Sentence',
            $activityType === 'read_with_coach' => 'Read with Miss Ciel',
            $activityType === 'timed_sentence_reading' => 'Smooth Sentence Reading',
            $activityType === 'pause_practice' => 'Pause Practice',
            $activityType === 'fluency_challenge' => 'Fluency Practice',
            default => $fallback,
        };
    }

    private function activityDescription(string $moduleKey, string $activityType): string
    {
        return match ($activityType) {
            'hear_and_repeat' => 'Listen to a sound, then say it back clearly.',
            'listen_and_say' => 'Listen carefully, then say the sound in your own voice.',
            'letter_sounds' => 'Practice the sound each letter makes.',
            'see_letter_say_sound' => 'Look at a letter and say the sound it makes.',
            'match_sound_to_letter' => 'Choose the letter that matches the sound you hear.',
            'sound_drill' => 'Practice tricky sounds a few times to make them stronger.',
            'read_word' => 'Read one word carefully and say it out loud.',
            'word_family_drill' => 'Practice words that share the same ending sound.',
            'minimal_pair' => 'Listen for small sound changes between similar words.',
            'word_accuracy_challenge' => 'Read words clearly and check each sound.',
            'read_sentence' => 'Read a short sentence from beginning to end.',
            'read_with_coach' => 'Practice a sentence with gentle help from Miss Ciel.',
            'timed_sentence_reading' => 'Read at a steady pace without rushing.',
            'pause_practice' => 'Practice stopping at the right places in a sentence.',
            'fluency_challenge' => 'Read smoothly so the sentence sounds natural.',
            default => 'Practice this reading step.',
        };
    }

    private function activityExplanation(string $moduleKey, string $activityType): string
    {
        return match ($activityType) {
            'hear_and_repeat' => 'This lesson helps your ears and voice work together. Listen first, then copy the sound clearly.',
            'listen_and_say' => 'This lesson helps you listen closely and say the sound clearly after you hear it.',
            'letter_sounds' => 'This lesson helps you learn the sound each letter makes so words become easier to read.',
            'see_letter_say_sound' => 'This lesson helps you remember what sound each letter makes when you see it.',
            'match_sound_to_letter' => 'This lesson helps you connect a sound you hear with the letter that makes it.',
            'sound_drill' => 'This lesson gives your mouth extra practice with sounds that need more time.',
            'read_word' => 'This lesson helps you look at a word and read it out loud one step at a time.',
            'word_family_drill' => 'This lesson helps you notice words that sound alike, like words with the same ending.',
            'minimal_pair' => 'This lesson helps you hear tiny sound changes so you can read similar words correctly.',
            'word_accuracy_challenge' => 'This lesson helps you slow down, check each sound, and read the whole word clearly.',
            'read_sentence' => 'This lesson helps you read a full sentence and keep the words in order.',
            'read_with_coach' => 'This lesson lets me guide you while you practice reading a sentence clearly.',
            'timed_sentence_reading' => 'This lesson helps you read smoothly at a steady pace, not too fast and not too slow.',
            'pause_practice' => 'This lesson helps you pause in the right places so the sentence makes sense.',
            'fluency_challenge' => 'This lesson helps your reading sound smooth and natural, like telling a story.',
            default => 'This part helps you practice reading in a clear way.',
        };
    }
}
