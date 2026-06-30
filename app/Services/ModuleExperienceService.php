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
            default => $fallback,
        };
    }

    private function activityDescription(string $moduleKey, string $activityType): string
    {
        if (($description = $this->moduleActivityDescription($moduleKey, $activityType)) !== null) {
            return $description;
        }

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
            default => 'Practice this reading step.',
        };
    }

    private function activityExplanation(string $moduleKey, string $activityType): string
    {
        if (($explanation = $this->moduleActivityExplanation($moduleKey, $activityType)) !== null) {
            return $explanation;
        }

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
            'hear_and_repeat' => 'Letter Sound Warm-Up',
            'listen_and_say' => 'Say the Letter Sound',
            'letter_sounds' => 'Build Letter Sounds',
            'see_letter_say_sound' => 'Look and Say',
            'match_sound_to_letter' => 'Letter Sound Check',
            'sound_drill' => 'Quick Sound Practice',
            default => null,
        };
    }

    private function moduleOneActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'hear_and_repeat' => 'Start with clear letter-sound practice.',
            'listen_and_say' => 'Look at the letter and say its sound clearly.',
            'letter_sounds' => 'Practice the basic sound each letter can make.',
            'see_letter_say_sound' => 'Look at the letter, then say its sound.',
            'match_sound_to_letter' => 'Check the sound that belongs with the letter.',
            'sound_drill' => 'Repeat letter sounds for faster recall.',
            default => null,
        };
    }

    private function moduleOneActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'hear_and_repeat' => 'This box warms up letter sounds. You look at each letter and say the sound clearly.',
            'listen_and_say' => 'This box is for saying the letter sound yourself. Look carefully, then use your clear voice.',
            'letter_sounds' => 'This box is for building the basic letter sounds before we use them in words.',
            'see_letter_say_sound' => 'This box is for matching your eyes and voice. Look at the letter, then say the sound it makes.',
            'match_sound_to_letter' => 'This box checks letter-sound recognition. Look at the letter and say the sound that matches it.',
            'sound_drill' => 'This box is for quick practice. You repeat letter sounds so the sound comes to mind faster.',
            default => null,
        };
    }

    private function moduleTwoActivityTitle(string $activityType): ?string
    {
        return match ($activityType) {
            'read_word' => 'Read One Word',
            'word_family_drill' => 'Word Family Practice',
            'minimal_pair' => 'Similar Word Practice',
            'word_accuracy_challenge' => 'Word Accuracy Check',
            default => null,
        };
    }

    private function moduleTwoActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'read_word' => 'Read short words clearly, one at a time.',
            'word_family_drill' => 'Read words that share the same ending pattern.',
            'minimal_pair' => 'Read similar-looking words with careful sounds.',
            'word_accuracy_challenge' => 'Read each word clearly and check every sound.',
            default => null,
        };
    }

    private function moduleTwoActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'read_word' => 'This box is for reading one short word at a time. Look at the word, then say the whole word clearly.',
            'word_family_drill' => 'This box groups words with the same ending sound. Notice the pattern, then read each word clearly.',
            'minimal_pair' => 'This box uses words that look or sound close to each other. Read carefully so every sound is clear.',
            'word_accuracy_challenge' => 'This box checks careful word reading. Slow down, look at each letter sound, then read the word.',
            default => null,
        };
    }

    private function moduleThreeActivityTitle(string $activityType): ?string
    {
        return match ($activityType) {
            'read_sentence' => 'Read One Sentence',
            'read_with_coach' => 'Guided Sentence Practice',
            'timed_sentence_reading' => 'Steady Sentence Reading',
            'pause_practice' => 'Pause and Pace Practice',
            default => null,
        };
    }

    private function moduleThreeActivityDescription(string $activityType): ?string
    {
        return match ($activityType) {
            'read_sentence' => 'Read the whole sentence clearly from start to finish.',
            'read_with_coach' => 'Read a sentence, then Miss Ciel can guide your next try.',
            'timed_sentence_reading' => 'Read at a steady pace without rushing.',
            'pause_practice' => 'Read with small pauses so the sentence makes sense.',
            default => null,
        };
    }

    private function moduleThreeActivityExplanation(string $activityType): ?string
    {
        return match ($activityType) {
            'read_sentence' => 'This box is for reading a full sentence. Keep the words in order and say each word clearly.',
            'read_with_coach' => 'This box gives you sentence practice with Miss Ciel nearby. Read the sentence clearly, and she will help after you try.',
            'timed_sentence_reading' => 'This box checks steady sentence reading. Read clearly at a calm pace, not too fast and not too slow.',
            'pause_practice' => 'This box is for pacing. Read the sentence with small pauses where they help the meaning.',
            default => null,
        };
    }
}
