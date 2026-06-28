<?php

namespace App\Services\VoiceLines;

class VoiceLineCatalog
{
    private const DURATION = [
        'target' => 7.0,
        'min' => 6.0,
        'max' => 9.0,
    ];

    private const DEFENSE_VALUES = [
        'cat',
        'dog',
        'sun',
        'map',
        'pen',
        'red',
        'log',
        'cup',
        'fish',
        'leaf',
        'kite',
        'seed',
        'mat',
        'sit',
        'run',
        'read',
        'Rosa',
        'Lena',
    ];

    public function staticLines(): array
    {
        return [
            $this->line('ciel.intro.read_together', 'ciel', 'intro', "Hi, I'm Miss Ciel. I'll read with you today, and we'll take each word slowly together.", 'Ciel introduction before reading practice', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.friendly.read_slowly_together', 'ciel', 'friendly_encouragement', "Take your time, then read this one out loud. I'll stay with you, and we can go slowly together.", 'Module coaching before learner reads', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.friendly.breathe_then_read', 'ciel', 'friendly_encouragement', "You can do it. Look at the word first, breathe softly, and then say it when you're ready.", 'Module coaching before recording', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.friendly.ready_read_together', 'ciel', 'friendly_encouragement', "Ready? Let's read this one together. Go slowly, and just try your best.", 'Light module guidance', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.reassurance.try_one_more_time', 'ciel', 'gentle_reassurance', "That's okay, let's try that one more time. This one can be tricky, but we can slow it down together.", 'Retry after incorrect or unclear attempt', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.reassurance.slow_down_together', 'ciel', 'gentle_reassurance', "No worries, you were close. Let's listen carefully, then say it again a little slower.", 'Near-miss feedback', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.reassurance.step_by_step_retry', 'ciel', 'gentle_reassurance', "That was close. We'll try it again together, and this time we'll take it step by step.", 'Near-miss retry', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.praise.clear_confident', 'ciel', 'happy_praise', "Nice work! You said that clearly, and I can hear that you're getting more confident.", 'Correct answer praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.praise.got_that_one', 'ciel', 'happy_praise', 'Great job! You got that one, and you read it with a nice clear voice.', 'Correct answer praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.praise.keep_going_clear', 'ciel', 'happy_praise', "Good job, that was clear. Let's keep going while you're doing so well.", 'Praise before next item', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.instruction.listen_then_say_word', 'ciel', 'focused_instruction', "Listen carefully first, then say the word after me. Take your time and speak clearly when you're ready.", 'Hear-and-repeat instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.instruction.look_listen_read', 'ciel', 'focused_instruction', 'Look at the word, listen to the sound, and then read it out loud in your own voice.', 'Displayed word instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.instruction.say_sound_clearly', 'ciel', 'focused_instruction', "When you're ready, say the sound clearly. We'll go slowly, so you don't need to rush.", 'Sound practice instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.playful.go_slowly_try', 'ciel', 'playful_friend', "Ready? Let's go slowly and give this one a try. I'll be right here with you.", 'Friendly module transition', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.playful.try_together_smile', 'ciel', 'playful_friend', "Let's try this one together. Look closely, smile a little, and say it when you're ready.", 'Light module guidance', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.playful.next_one_clear', 'ciel', 'playful_friend', "Nice, let's move to the next one. Keep your voice clear and take your time.", 'Transition after item completion', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.asr.success_generic', 'ciel', 'friendly_encouragement', "I heard your answer clearly. Let's check it together and keep going.", 'Module ASR success without fixed transcript fixture', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.asr.transcript_unknown', 'ciel', 'gentle_reassurance', "You said your answer clearly. Let's continue with the next reading step.", 'Module ASR transcript fallback', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.mastery.start', 'ciel', 'friendly_encouragement', "This is your mini mastery check. Do your best one item at a time, and I'll stay with you.", 'Mini mastery start prompt', 'resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue'),
            $this->line('ciel.automatic.stopped', 'ciel', 'gentle_reassurance', 'Ciel stopped listening safely. You can use Manual Recording Mode and keep practicing at your own pace.', 'Automatic listening stopped fallback', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),

            $this->line('ciel.module.processing.checking_reading', 'ciel', 'focused_instruction', 'I am checking your reading now. Please wait a moment while I listen carefully.', 'Module processing status', 'ReaDirect-IA/dialogue/ciel.yaml'),
            $this->line('ciel.module.audio_unclear.try_clear_voice', 'ciel', 'gentle_reassurance', 'I could not hear that clearly. That is okay, so please try again with your clear reading voice.', 'Unclear recording retry', 'ReaDirect-IA/dialogue/ciel.yaml'),
            $this->line('ciel.module.retry.first_sound', 'ciel', 'focused_instruction', 'Almost there. Listen to the first sound, then try the word again slowly and clearly.', 'Initial sound correction', 'ReaDirect-IA/dialogue/ciel.yaml'),
            $this->line('ciel.module.retry.middle_sound', 'ciel', 'focused_instruction', 'Almost there. Listen to the middle sound, then say it again a little slower.', 'Middle sound correction', 'ReaDirect-IA/dialogue/ciel.yaml'),
            $this->line('ciel.module.retry.final_sound', 'ciel', 'focused_instruction', 'Almost there. Say the ending sound clearly this time, then finish the whole word carefully.', 'Final sound correction', 'ReaDirect-IA/dialogue/ciel.yaml'),
            $this->line('ciel.module.reward.three_correct', 'ciel', 'happy_praise', 'Great job! You answered three in a row correctly, and your clear voice is getting stronger.', 'Focus mode reward', 'app/Services/CielFocusModeService.php'),
            $this->line('ciel.module.reward.star_earned', 'ciel', 'happy_praise', 'You earned a star! Keep going slowly, and keep trying your best on each reading step.', 'Focus mode reward', 'app/Services/CielFocusModeService.php'),
            $this->line('ciel.module.goodbye', 'ciel', 'playful_friend', 'See you next time. Keep practicing, keep using your clear voice, and remember that every step helps.', 'Module goodbye', 'app/Services/ModuleExperienceService.php'),

            $this->line('vivian.intro.assessment', 'vivian', 'intro', "Hi, I'm Miss Vivian. I'll guide you through this activity, so listen carefully and take your time.", 'Assessment introduction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.instruction.listen_then_say_sound', 'vivian', 'focused_instruction', "Listen carefully first, then say the sound out loud. When you're ready, use a clear voice.", 'Assessment sound instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.instruction.look_item_answer', 'vivian', 'focused_instruction', 'Look at the item on the screen, listen to the instruction, and answer when you are ready.', 'Assessment item instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.instruction.listen_choose_or_say', 'vivian', 'focused_instruction', 'Take your time before you answer. Listen first, then choose or say the response clearly.', 'Assessment choice or spoken response', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.friendly.stay_focused_ready', 'vivian', 'friendly_encouragement', "You're doing fine. Just stay focused, listen carefully, and answer when you feel ready.", 'Assessment encouragement', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.friendly.keep_going_each_item', 'vivian', 'friendly_encouragement', 'Keep going. Take your time with each item, and remember to listen before you answer.', 'Assessment transition', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.friendly.one_item_at_time', 'vivian', 'friendly_encouragement', 'You can do this. Stay calm, look carefully, and answer one item at a time.', 'Assessment start or transition', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.reassurance.next_item_clear_voice', 'vivian', 'gentle_reassurance', "That's okay. Just listen carefully and try the next item with a calm and clear voice.", 'Assessment retry', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.reassurance.continue_calmly', 'vivian', 'gentle_reassurance', "No worries. Stay focused, take your time, and let's continue with the next one.", 'Assessment continuation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.reassurance.tricky_keep_going', 'vivian', 'gentle_reassurance', 'That one was a bit tricky. Keep going, and remember to listen carefully first.', 'Assessment reassurance', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.praise.answered_clearly', 'vivian', 'happy_praise', "Nice work. You answered that clearly, so let's keep moving through the activity.", 'Assessment praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.praise.clear_voice_next', 'vivian', 'happy_praise', 'Good job. Stay focused and keep using your clear voice for the next item.', 'Assessment praise transition', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.praise.one_step_time', 'vivian', 'happy_praise', "Great work. You're doing well, and we'll continue one step at a time.", 'Assessment praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('vivian.assessment.final_start', 'vivian', 'focused_instruction', 'This is your final reading check. Do your best, one step at a time.', 'Final assessment start', 'resources/js/Pages/Learner/FinalAssessment/Start.vue'),
            $this->line('vivian.assessment.story_choice', 'vivian', 'focused_instruction', 'Choose one story for your final reading passage.', 'Story selection', 'resources/js/Pages/Learner/FinalAssessment/StorySelection.vue'),
            $this->line('vivian.assessment.comprehension_choice', 'vivian', 'focused_instruction', 'Choose the best answer based on the story you read.', 'Comprehension question instruction', 'resources/js/Pages/Learner/FinalAssessment/ComprehensionQuestions.vue'),
            $this->line('vivian.task1.normal_start', 'vivian', 'focused_instruction', 'Say the letter out loud when you are ready. Use a clear voice, and take your time before you record.', 'Diagnostic Task 1 item start', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.task2a.rhyme_prompt_intro', 'vivian', 'focused_instruction', 'Listen to both words carefully. Then choose Yes if they rhyme, or No if they do not rhyme.', 'Task 2A rhyme item prompt', 'resources/js/Pages/Learner/Task2ARhymingWords.vue'),
            $this->line('vivian.task2b.word_sentence_start', 'vivian', 'focused_instruction', 'Read the highlighted word in the sentence. When you are ready, record it clearly.', 'Task 2B item start', 'resources/js/Components/Learner/Task2BWordAssessment.vue'),
            $this->line('vivian.passage.after_recording', 'vivian', 'focused_instruction', 'Listen to your reading first. If you are happy with it, you can submit your answer.', 'Passage recording review prompt', 'resources/js/Components/Learner/PassageReadingAssessment.vue'),
            $this->line('vivian.processing.checking_recording', 'vivian', 'focused_instruction', 'I am checking your recording now. Please wait a moment while I listen carefully.', 'Assessment recording processing status', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.processing.checking_answer', 'vivian', 'focused_instruction', 'I am checking your answer now. Please wait while I review it.', 'Assessment answer processing status', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.processing.checking_reading', 'vivian', 'focused_instruction', 'I am checking your reading now. This may take a moment, so please wait.', 'Passage reading processing status', 'resources/js/Components/Learner/PassageReadingAssessment.vue'),
            $this->line('vivian.continue.thank_you', 'vivian', 'friendly_encouragement', "Thank you. Let's continue to the next item when you are ready.", 'Assessment transition after saved answer', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.continue.good_effort', 'vivian', 'friendly_encouragement', "Good effort. Let's go to the next one and keep doing our best.", 'Assessment transition after saved answer', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.asr.unknown_transcript', 'vivian', 'gentle_reassurance', "I heard your answer, but I do not have that line ready yet. Let's keep going carefully.", 'Vivian ASR transcript fallback', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.no_recording.passage_first', 'vivian', 'gentle_reassurance', 'Hold the orange button to record the passage first, then submit when you are ready.', 'Passage no recording warning', 'resources/js/Components/Learner/PassageReadingAssessment.vue'),
            $this->line('vivian.error.recording_check_failed', 'vivian', 'gentle_reassurance', "Something went wrong while checking your recording. That's okay, please try again with a clear voice.", 'Assessment recording check error', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),

            $this->line('estelle.intro.results', 'estelle', 'intro', "Hi, I'm Miss Estelle. I'll help you look at your results in a calm and simple way.", 'Results introduction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.evaluation.look_result_together', 'estelle', 'calm_evaluation', "Let's look at your result together. This will help us understand what you already do well and what we can practice next.", 'Result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.evaluation.did_well_tricky_items', 'estelle', 'calm_evaluation', 'You did well in this part, and there are still a few tricky items that we can keep practicing.', 'Result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.evaluation.result_guides_support', 'estelle', 'calm_evaluation', 'This result is here to help us. It shows where you are improving and where you may need more support.', 'Result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.reassurance.result_not_failed', 'estelle', 'gentle_reassurance', 'That is okay. This result does not mean you failed; it simply helps us know what to practice next.', 'Reassuring result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.reassurance.parts_are_tricky', 'estelle', 'gentle_reassurance', 'No worries. Some parts can be tricky, and we can use this result to help you improve.', 'Reassuring result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.reassurance.guide_next_practice', 'estelle', 'gentle_reassurance', "It's okay if some items were hard. We'll use this result to guide your next practice.", 'Reassuring result explanation', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.praise.effort_progress', 'estelle', 'happy_praise', 'Great job. You showed good effort, and your result shows that you are making progress.', 'Results praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.praise.proud_effort', 'estelle', 'happy_praise', 'Nice work. You did well in this part, and you should feel proud of your effort.', 'Results praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.praise.stayed_focused', 'estelle', 'happy_praise', 'Good job. You stayed focused, and that helped you complete this activity.', 'Results praise', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.instruction.look_result_carefully', 'estelle', 'focused_instruction', "Please look at the result carefully. I'll explain it in a simple way so it is easy to understand.", 'Results instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.instruction.go_through_slowly', 'estelle', 'focused_instruction', "Let's go through this part slowly. I'll help you understand what the result means.", 'Results instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.instruction.score_then_practice', 'estelle', 'focused_instruction', "Take a moment to look at your score, then we'll talk about what you can practice next.", 'Results instruction', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('estelle.completion.final_check_complete', 'estelle', 'calm_evaluation', 'Great job finishing your final reading check. Your effort shows what you practiced and what you can keep building next.', 'Completion summary', 'app/Http/Controllers/Learner/LearnerCompletionController.php'),
            $this->line('estelle.summary.progress_made', 'estelle', 'calm_evaluation', 'You made progress from your first reading check to your final reading check, and that progress can guide your next practice.', 'Completion progress summary', 'app/Http/Controllers/Learner/LearnerCompletionController.php'),
            $this->line('estelle.result.task1.routing', 'estelle', 'calm_evaluation', 'You finished the first reading task. Your score helps us decide which reading activity should come next.', 'Task 1 routing result voice line', 'resources/js/Pages/Learner/TaskRoutingResult.vue'),
            $this->line('estelle.result.task2a.saved', 'estelle', 'calm_evaluation', 'Task 2A is now saved. Based on this path, the next reading parts will not be given for now.', 'Task 2A summary result voice line', 'resources/js/Pages/Learner/Task2ASummary.vue'),
            $this->line('estelle.result.crla.summary_with_passage', 'estelle', 'calm_evaluation', 'The CRLA tasks are complete. Review your scores first, then you will continue with a short reading passage.', 'CRLA summary with passage voice line', 'resources/js/Pages/Learner/CrlaSummary.vue'),
            $this->line('estelle.result.crla.summary_no_passage', 'estelle', 'calm_evaluation', 'The CRLA tasks are complete. Passage reading is not needed for this result, so we can move forward.', 'CRLA summary without passage voice line', 'resources/js/Pages/Learner/CrlaSummary.vue'),
            $this->line('estelle.result.reading_summary', 'estelle', 'calm_evaluation', 'I used your final reading score to find your reading level. Tap continue when you are ready to see your path.', 'Reading summary result voice line', 'resources/js/Pages/Learner/ReadingSummary.vue'),
            $this->line('estelle.result.module_placement', 'estelle', 'calm_evaluation', 'Great job. Your reading path is ready, and it will guide the next activities on your dashboard.', 'Module placement result voice line', 'resources/js/Pages/Learner/ModulePlacementResult.vue'),
            $this->line('estelle.result.grade_level_placement', 'estelle', 'happy_praise', 'Wonderful work. You are reading at grade level, so you can continue to your dashboard.', 'Grade-level placement result voice line', 'resources/js/Pages/Learner/ModulePlacementResult.vue'),
            $this->line('estelle.result.mastery_ready', 'estelle', 'calm_evaluation', 'Your mastery result is ready. This helps us see what you learned and what you can practice next.', 'Module mastery result voice line', 'resources/js/Pages/Learner/Modules/ModuleMasteryResult.vue'),
        ];
    }

    public function dynamicTemplates(): array
    {
        return [
            $this->template('asr_echo.generic', 'vivian', 'focused_instruction', 'I heard: {transcript}.', 'ASR transcript echo template'),
            $this->template('learner_echo.generic', 'vivian', 'focused_instruction', 'You said: {transcript}.', 'Learner transcript echo template'),
            $this->template('target_word_echo.generic', 'ciel', 'focused_instruction', 'The word is {target_word}.', 'Target word echo template'),
            $this->template('try_again_with_target.generic', 'ciel', 'gentle_reassurance', "That's okay, let's try {target_word} one more time.", 'Targeted retry template'),
            $this->template('correct_word_support.generic', 'ciel', 'focused_instruction', "The correct word is {target_word}. Let's say it slowly together.", 'Correct word support template'),
        ];
    }

    public function defenseFixtures(): array
    {
        $fixtures = [];
        foreach ($this->dynamicTemplates() as $template) {
            foreach (self::DEFENSE_VALUES as $value) {
                $field = str_contains($template['text'], '{transcript}') ? 'transcript' : 'target_word';
                $text = str_replace('{'.$field.'}', $value, $template['text']);
                $fixtures[] = $this->line(
                    $template['line_key'].'.'.strtolower($value),
                    $template['agent'],
                    $template['intent'],
                    $text,
                    'Defense fixture for '.$template['line_key'],
                    'voice_line_dynamic_fixture',
                    isDefenseDemo: true
                );
            }
        }

        return $fixtures;
    }

    public function allSeedLines(): array
    {
        return [
            ...$this->staticLines(),
            ...$this->dynamicTemplates(),
            ...$this->defenseFixtures(),
        ];
    }

    private function line(
        string $lineKey,
        string $agent,
        string $intent,
        string $text,
        string $context,
        string $sourceFile,
        bool $isDefenseDemo = false,
    ): array {
        return [
            'line_key' => $lineKey,
            'agent' => $agent,
            'intent' => $intent,
            'context' => $context,
            'source_repo' => str_starts_with($sourceFile, 'ReaDirect-TTS') ? 'ReaDirect-TTS' : 'ReaDirect',
            'source_file' => $sourceFile,
            'text' => $text,
            'target_duration_seconds' => self::DURATION['target'],
            'min_duration_seconds' => self::DURATION['min'],
            'max_duration_seconds' => self::DURATION['max'],
            'protected' => false,
            'is_static' => true,
            'is_dynamic_template' => false,
            'is_defense_demo' => $isDefenseDemo,
            'fallback_only' => false,
            'generate_two_stage' => true,
        ];
    }

    private function template(string $lineKey, string $agent, string $intent, string $text, string $context): array
    {
        return [
            'line_key' => $lineKey,
            'agent' => $agent,
            'intent' => $intent,
            'context' => $context,
            'source_repo' => 'ReaDirect',
            'source_file' => 'dynamic_voice_line_template',
            'text' => $text,
            'target_duration_seconds' => self::DURATION['target'],
            'min_duration_seconds' => self::DURATION['min'],
            'max_duration_seconds' => self::DURATION['max'],
            'protected' => true,
            'is_static' => false,
            'is_dynamic_template' => true,
            'is_defense_demo' => false,
            'fallback_only' => true,
            'generate_two_stage' => false,
        ];
    }
}
