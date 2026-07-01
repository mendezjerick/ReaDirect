<?php

namespace App\Services\VoiceLines;

class VoiceLineCatalog
{
    private readonly ModuleEchoLineFactory $moduleEchoLines;

    public function __construct(?ModuleEchoLineFactory $moduleEchoLines = null)
    {
        $this->moduleEchoLines = $moduleEchoLines ?? new ModuleEchoLineFactory();
    }

    private const DURATION = [
        'target' => 7.0,
        'min' => 6.0,
        'max' => 9.0,
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
            $this->line('ciel.module1.letter_pair_identification.01', 'ciel', 'focused_instruction', 'Look at the letter pair below, then say the letter clearly.', 'Module 1 lesson 1 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.letter_pair_identification.02', 'ciel', 'focused_instruction', 'Read the letter you see. Say it nice and clear.', 'Module 1 lesson 1 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.letter_pair_identification.03', 'ciel', 'focused_instruction', "Let's practice this letter. Say the letter shown below.", 'Module 1 lesson 1 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.highlighted_first_letter.01', 'ciel', 'focused_instruction', 'Look at the highlighted first letter, then say that letter.', 'Module 1 lesson 2 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.highlighted_first_letter.02', 'ciel', 'focused_instruction', 'The first letter is highlighted. Say the letter that starts the word.', 'Module 1 lesson 2 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.highlighted_first_letter.03', 'ciel', 'focused_instruction', 'Find the highlighted letter at the start of the word, then say it clearly.', 'Module 1 lesson 2 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.first_letter_identification.01', 'ciel', 'focused_instruction', 'Look at the word below. What letter does it start with?', 'Module 1 lesson 3 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.first_letter_identification.02', 'ciel', 'focused_instruction', 'Find the first letter of the word, then say it clearly.', 'Module 1 lesson 3 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.first_letter_identification.03', 'ciel', 'focused_instruction', 'No highlight this time. Say the letter that starts the word.', 'Module 1 lesson 3 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.missing_first_letter.01', 'ciel', 'focused_instruction', 'Look at the full word and the missing-letter word. What letter is missing?', 'Module 1 lesson 4 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.missing_first_letter.02', 'ciel', 'focused_instruction', 'Compare the two words, then say the missing first letter.', 'Module 1 lesson 4 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.missing_first_letter.03', 'ciel', 'focused_instruction', 'The first letter is missing. Say the letter that completes the word.', 'Module 1 lesson 4 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.display_word_reading.01', 'ciel', 'focused_instruction', 'Look at the word below, then read it clearly.', 'Module 2 lesson 1 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.display_word_reading.02', 'ciel', 'focused_instruction', 'Read the word you see on the screen.', 'Module 2 lesson 1 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.display_word_reading.03', 'ciel', 'focused_instruction', "Let's practice this word. Say the word clearly.", 'Module 2 lesson 1 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.split_word_reading.01', 'ciel', 'focused_instruction', 'Look at the word parts, then read the whole word.', 'Module 2 lesson 2 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.split_word_reading.02', 'ciel', 'focused_instruction', 'Blend the parts together and say the full word.', 'Module 2 lesson 2 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.split_word_reading.03', 'ciel', 'focused_instruction', 'Read the complete word made by the parts below.', 'Module 2 lesson 2 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_rhyme_word.01', 'ciel', 'focused_instruction', 'Look at the rhyming words, then read the highlighted word.', 'Module 2 lesson 3 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_rhyme_word.02', 'ciel', 'focused_instruction', 'Only read the highlighted word in the group.', 'Module 2 lesson 3 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_rhyme_word.03', 'ciel', 'focused_instruction', 'Find the highlighted word, then say it clearly.', 'Module 2 lesson 3 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_sentence_word.01', 'ciel', 'focused_instruction', 'Look at the sentence, then read the highlighted word.', 'Module 2 lesson 4 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_sentence_word.02', 'ciel', 'focused_instruction', 'Only say the highlighted word in the sentence.', 'Module 2 lesson 4 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.highlighted_sentence_word.03', 'ciel', 'focused_instruction', 'Find the highlighted word in the sentence and read it clearly.', 'Module 2 lesson 4 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.simple_sentence_reading.01', 'ciel', 'focused_instruction', 'Read the sentence below from start to finish.', 'Module 3 lesson 1 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.simple_sentence_reading.02', 'ciel', 'focused_instruction', 'Look at the sentence, then read it clearly.', 'Module 3 lesson 1 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.simple_sentence_reading.03', 'ciel', 'focused_instruction', "Let's read the whole sentence carefully.", 'Module 3 lesson 1 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.comma_pause_reading.01', 'ciel', 'focused_instruction', 'Read the sentence and make a small pause at the comma.', 'Module 3 lesson 2 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.comma_pause_reading.02', 'ciel', 'focused_instruction', 'When you see the comma, pause just a little, then keep reading.', 'Module 3 lesson 2 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.comma_pause_reading.03', 'ciel', 'focused_instruction', 'Read smoothly and remember the small comma pause.', 'Module 3 lesson 2 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.full_stop_pause_reading.01', 'ciel', 'focused_instruction', 'Read both sentences and pause after the full stop.', 'Module 3 lesson 3 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.full_stop_pause_reading.02', 'ciel', 'focused_instruction', 'When the sentence ends, make a stronger pause before reading the next one.', 'Module 3 lesson 3 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.full_stop_pause_reading.03', 'ciel', 'focused_instruction', 'Read the two sentences clearly, with a full-stop pause between them.', 'Module 3 lesson 3 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.mixed_punctuation_fluency.01', 'ciel', 'focused_instruction', 'Read the full text smoothly. Pause at the comma and full stop.', 'Module 3 lesson 4 instruction cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.mixed_punctuation_fluency.02', 'ciel', 'focused_instruction', 'Read carefully and use the punctuation to guide your pacing.', 'Module 3 lesson 4 instruction cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.mixed_punctuation_fluency.03', 'ciel', 'focused_instruction', "Let's read this smoothly, with clear pauses and steady pacing.", 'Module 3 lesson 4 instruction cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module1.overview.intro', 'ciel', 'focused_instruction', 'Hi, I am Miss Ciel. Choose a lesson box, and I will explain how it helps your letter practice.', 'Module 1 overview intro', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module2.overview.intro', 'ciel', 'focused_instruction', 'Hi, I am Miss Ciel. Choose a lesson box, and I will show how each word practice step works.', 'Module 2 overview intro', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module3.overview.intro', 'ciel', 'focused_instruction', 'Hi, I am Miss Ciel. Choose a lesson box, and I will explain how it helps your sentence reading.', 'Module 3 overview intro', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module_overview.intro', 'ciel', 'focused_instruction', 'Hi, I am Miss Ciel. Choose a lesson box, and I will guide your reading practice.', 'Generic module overview intro', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module_overview.transition.found_another', 'ciel', 'focused_instruction', 'I see you found another lesson. I will explain this one clearly too.', 'Module overview hover transition', 'resources/js/Pages/Learner/Modules/ModuleOverview.vue'),
            $this->line('ciel.module_overview.transition.look_next', 'ciel', 'focused_instruction', 'Let us look at this lesson next, so you know what to practice.', 'Module overview hover transition', 'resources/js/Pages/Learner/Modules/ModuleOverview.vue'),
            $this->line('ciel.module_overview.transition.different_box', 'ciel', 'focused_instruction', 'You found a different practice box. I will tell you what it helps with.', 'Module overview hover transition', 'resources/js/Pages/Learner/Modules/ModuleOverview.vue'),
            $this->line('ciel.module_overview.transition.next_one', 'ciel', 'focused_instruction', 'Good noticing. Here is the next lesson, and what you will do in it.', 'Module overview hover transition', 'resources/js/Pages/Learner/Modules/ModuleOverview.vue'),
            $this->line(
                'ciel.module1.overview.letter_pair_identification',
                'ciel',
                'focused_instruction',
                'This lesson shows the big and small form together, like Aa. Look first, then say the letter name clearly.',
                'Module 1 overview lesson hover',
                'app/Services/ModuleExperienceService.php',
                false,
                'This lesson shows the big and small form together, like ay. Look first, then say the letter name clearly.',
            ),
            $this->line('ciel.module1.overview.highlighted_first_letter', 'ciel', 'focused_instruction', 'This lesson gives you a word and marks the first letter. Find that marked letter, then say its name clearly.', 'Module 1 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module1.overview.first_letter_identification', 'ciel', 'focused_instruction', 'This lesson has no highlight. Look at the word, find the first letter yourself, then say that letter.', 'Module 1 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module1.overview.missing_first_letter', 'ciel', 'focused_instruction', 'This lesson shows the complete word and the same word with the first letter missing. Say the missing letter.', 'Module 1 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module2.overview.display_word_reading', 'ciel', 'focused_instruction', 'This lesson shows one short word. Look across the letters, blend the sounds, then read the word clearly.', 'Module 2 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module2.overview.split_word_reading', 'ciel', 'focused_instruction', 'This lesson breaks the word into two parts. Say the parts together, then read the whole word smoothly.', 'Module 2 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module2.overview.highlighted_rhyme_word', 'ciel', 'focused_instruction', 'This lesson shows words that sound alike. Look for the highlighted word, then read only that word.', 'Module 2 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module2.overview.highlighted_sentence_word', 'ciel', 'focused_instruction', 'This lesson puts the word inside a sentence. Find the highlighted word, then read just that word clearly.', 'Module 2 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module3.overview.simple_sentence_reading', 'ciel', 'focused_instruction', 'This lesson gives you one sentence. Read every word in order, from the first word to the last.', 'Module 3 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module3.overview.comma_pause_reading', 'ciel', 'focused_instruction', 'This lesson has a comma in the sentence. When you reach it, make a tiny pause, then keep reading.', 'Module 3 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module3.overview.full_stop_pause_reading', 'ciel', 'focused_instruction', 'This lesson uses two sentences. Pause when the first sentence ends, then begin the next sentence clearly.', 'Module 3 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module3.overview.mixed_punctuation_fluency', 'ciel', 'focused_instruction', 'This lesson mixes commas and full stops. Read every word clearly, and let the marks guide your pauses.', 'Module 3 overview lesson hover', 'app/Services/ModuleExperienceService.php'),
            $this->line('ciel.module1.validation.record_letter_first', 'ciel', 'focused_instruction', 'Please record the letter sound first.', 'Module 1 missing recording prompt', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module2.validation.record_word_first', 'ciel', 'focused_instruction', 'Please record the word first.', 'Module 2 missing recording prompt', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module3.validation.record_sentence_first', 'ciel', 'focused_instruction', 'Please record the sentence first.', 'Module 3 missing recording prompt', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.playful.go_slowly_try', 'ciel', 'playful_friend', "Ready? Let's go slowly and give this one a try. I'll be right here with you.", 'Friendly module transition', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.playful.try_together_smile', 'ciel', 'playful_friend', "Let's try this one together. Look closely, smile a little, and say it when you're ready.", 'Light module guidance', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.playful.next_one_clear', 'ciel', 'playful_friend', "Nice, let's move to the next one. Keep your voice clear and take your time.", 'Transition after item completion', 'ReaDirect-TTS/curated_agent_lines.py'),
            $this->line('ciel.asr.success_generic', 'ciel', 'friendly_encouragement', "I heard your answer clearly. Let's check it together and keep going.", 'Module ASR success without fixed transcript fixture', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.asr.transcript_unknown', 'ciel', 'gentle_reassurance', "You said your answer clearly. Let's continue with the next reading step.", 'Module ASR transcript fallback', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.mastery.start', 'ciel', 'friendly_encouragement', "This is your mini mastery check. Do your best one item at a time, and I'll stay with you.", 'Mini mastery start prompt', 'resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue'),
            $this->line('ciel.automatic.stopped', 'ciel', 'gentle_reassurance', 'Ciel stopped listening safely. You can use Manual Recording Mode and keep practicing at your own pace.', 'Automatic listening stopped fallback', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module.after_recording.review_submit_01', 'ciel', 'focused_instruction', 'Listen to your recording, then click Submit when you are ready.', 'Module recording review prompt cycle 1', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module.after_recording.review_submit_02', 'ciel', 'focused_instruction', 'You can review your audio first, then press Submit.', 'Module recording review prompt cycle 2', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),
            $this->line('ciel.module.after_recording.review_submit_03', 'ciel', 'focused_instruction', 'Play your recording if you want to check it, then submit your answer.', 'Module recording review prompt cycle 3', 'resources/js/Pages/Learner/Modules/ModuleActivity.vue'),

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
            $this->line('vivian.task2a.pair.t2a_d001', 'vivian', 'focused_instruction', 'cat, hat.', 'Task 2A rhyme pair T2A-D001', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d002', 'vivian', 'focused_instruction', 'sun, run.', 'Task 2A rhyme pair T2A-D002', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d003', 'vivian', 'focused_instruction', 'dog, log.', 'Task 2A rhyme pair T2A-D003', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d004', 'vivian', 'focused_instruction', 'cup, pup.', 'Task 2A rhyme pair T2A-D004', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d005', 'vivian', 'focused_instruction', 'bed, red.', 'Task 2A rhyme pair T2A-D005', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d006', 'vivian', 'focused_instruction', 'hop, top.', 'Task 2A rhyme pair T2A-D006', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d007', 'vivian', 'focused_instruction', 'map, sit.', 'Task 2A rhyme pair T2A-D007', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d008', 'vivian', 'focused_instruction', 'pen, bug.', 'Task 2A rhyme pair T2A-D008', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d009', 'vivian', 'focused_instruction', 'bat, lip.', 'Task 2A rhyme pair T2A-D009', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2a.pair.t2a_d010', 'vivian', 'focused_instruction', 'hen, tap.', 'Task 2A rhyme pair T2A-D010', 'database/seed-data/readirect/task2a_rhyming_words.csv'),
            $this->line('vivian.task2b.word_sentence_start', 'vivian', 'focused_instruction', 'Read the highlighted word in the sentence. When you are ready, record it clearly.', 'Task 2B item start', 'resources/js/Components/Learner/Task2BWordAssessment.vue'),
            $this->line('vivian.tutorial.stage1.intro', 'vivian', 'focused_instruction', 'Welcome. Before we begin the diagnostic activity, I will guide you through a short tutorial so you know exactly what to do. I will show the important parts of the page, and demonstrate how to record, listen, retry, and submit your answer. When you are ready to begin, click the Start button.', 'Diagnostic tutorial intro', 'resources/js/Pages/Learner/DiagnosticTutorial.vue'),
            $this->line('vivian.tutorial.stage1.vivian_location', 'vivian', 'focused_instruction', 'This is where we are located. You will see me here while I guide you through the activity, and you will also meet my fellow teachers along the way. We are here to give instructions, help you understand what to do, remind you to take your time, and make the speaking activity easier to follow from the beginning to the end.', 'Diagnostic tutorial teacher location', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'This is where we are located. You will see me here while I guide you through the activity. You will also meet my fellow teachers along the way. We give instructions, help you understand what to do, remind you to take your time, and make the speaking activity easier to follow from beginning, to end.'),
            $this->line('vivian.tutorial.stage1.dialogue_box', 'vivian', 'focused_instruction', 'This box is the dialogue box. This is where the instructions will appear while you are answering. It also shows the words that your teachers are saying, so if you need to read the instruction again, you can look here, follow what is written, and check what you should do before you record.', 'Diagnostic tutorial dialogue box', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'This box is the dialogue box. This is where instructions appear while you are answering. It also shows the words your teachers are saying. If you need to read the instruction again, look here, follow what is written, and check what to do before you record your answer clearly.'),
            $this->line('vivian.tutorial.stage1.item_display', 'vivian', 'focused_instruction', 'This is the item display area. This is where the letter, word, or sentence that you need to say will appear. For this tutorial, our example word is Sun. When you see an item here, read it carefully first, look for the part you need to say, and only record when you feel ready.', 'Diagnostic tutorial item display', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'This is the item display area. This is where the letter, word, or sentence that you need to say will appear. For this tutorial, our example word is sun. When you see an item here, read it carefully first, look for the part you need to say, and only record when you feel ready.'),
            $this->line('vivian.tutorial.stage1.recorder', 'vivian', 'focused_instruction', 'This button is the recorder button. This is where you record your answer. When it is your turn, hold the button, say the item clearly, and then release the button when you are done speaking. Try to speak loudly enough, keep your voice steady, and avoid background noise as much as you can.', 'Diagnostic tutorial recorder button', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'This button is the recorder button. This is where you record your answer. When it is your turn, hold the button, say the item clearly, and release it when you are done speaking. Speak loudly enough, keep your voice steady, and avoid background noise as much as you can today.'),
            $this->line('vivian.tutorial.stage1.demonstration_setup', 'vivian', 'focused_instruction', 'I will demonstrate it for you now. Watch the recorder button carefully. I will hold it down, say the word that appears on the screen, and then release the button after I finish speaking. This is the same process you will do during the activity, so follow the movement slowly and notice each step.', 'Diagnostic tutorial demonstration setup', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'I will demonstrate it for you now. Watch the recorder button carefully. I will hold it down, say the word on the screen, and release the button after I finish speaking. This is the same process you will do during the activity. Follow the movement slowly, and notice each step from beginning to end.'),
            $this->line('vivian.tutorial.stage1.demo_word', 'vivian', 'happy_praise', 'Sun.', 'Diagnostic tutorial demo word recording', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'sun'),
            $this->line('vivian.tutorial.stage1.playback', 'vivian', 'focused_instruction', 'After recording, you can click the recorder button again to listen to your own recording. This helps you check if your voice was captured clearly. If the recording sounds good and you are happy with your answer, then you can continue to the next step with confidence and without rushing.', 'Diagnostic tutorial playback', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'After you record, click the recorder button again to hear your own recording. Listen to see if your voice was clear, loud, and easy to understand. If the sound is good, and you are happy with your answer, you can move to the next step. Take your time, listen carefully, and do not rush.'),
            $this->line('vivian.tutorial.stage1.retry', 'vivian', 'focused_instruction', 'If you are not satisfied with your recording, you can click Retry and record again. Use this when your voice was too soft, when there was noise, or when you feel that you did not say the item clearly. You can try again before submitting, so it is fine to pause and make another recording.', 'Diagnostic tutorial retry', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'If you are not satisfied with your recording, click Retry and record again. Use this when your voice was too soft, when there was noise, or when the item was not clear. That is okay. Pause, take your time, breathe once, and make another recording before submitting your answer.'),
            $this->line('vivian.tutorial.stage1.submit', 'vivian', 'focused_instruction', 'When you are ready and you are satisfied with your recording, this is the button you will click to submit your audio. Submitting means the system will listen to your recording and check what it heard, so make sure your answer is clear, your voice is finished, and you feel ready before clicking it.', 'Diagnostic tutorial submit button', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'When you are ready, and you are satisfied with your recording, click this button to submit your audio. Submitting means the system will listen to your recording and check what it heard. Make sure your answer is clear. Make sure your voice is finished, and you feel ready before clicking it.'),
            $this->line('vivian.tutorial.stage1.transcript_result', 'vivian', 'focused_instruction', 'After submitting, this same display area will show what the system heard you say. This is also where we will base if your answer is correct or not. Look at this part carefully, because it helps show whether the spoken answer matched the item and whether you can move forward.', 'Diagnostic tutorial merged transcript display', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'After submitting, this same display area will show what the system heard you say. This is also where we will base if your answer is correct or not. Pause here and look carefully, because it helps show whether the spoken answer matched the item, and whether you are ready to move forward.'),
            $this->line('vivian.tutorial.stage1.manual_override', 'vivian', 'focused_instruction', 'As for my fellow teachers, you can use this manual transcript area if our system is having trouble recognizing the audio. If the learner said the answer correctly but the system heard something different, you can type what the learner actually said here, so the review can match the real spoken answer.', 'Diagnostic tutorial manual transcript override', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'As for my fellow teachers, use this manual transcript area if the system has trouble recognizing audio. If the learner said the answer correctly, but the system heard something different, type what the learner actually said here. This helps the review match the real spoken answer clearly and fairly.'),
            $this->line('vivian.tutorial.stage1.next_button', 'vivian', 'focused_instruction', 'After the answer has been checked, this button will let you move to the next item. Click it only when you are ready to continue. Each item follows the same process, so remember to read, record, listen if needed, retry if needed, and submit before going on.', 'Diagnostic tutorial next button', 'resources/js/Pages/Learner/DiagnosticTutorial.vue', false, 'After the answer has been checked, this button will move you to the next item on the page. Click it only when you are ready to continue, and do not rush. Each item uses the same calm process. Remember to read, record, listen if needed, retry if needed, and submit before going on to the next item.'),
            $this->line('vivian.tutorial.stage1.farewell', 'vivian', 'focused_instruction', 'That is the tutorial. Now you know how to use the speaking activity page and what each part is for. Take your time, speak clearly, and do your best. When the next page begins, remember that each item follows this same calm routine. I believe in you, and I know you can do it.', 'Diagnostic tutorial farewell', 'resources/js/Pages/Learner/DiagnosticTutorial.vue'),
            $this->line('vivian.passage.after_recording', 'vivian', 'focused_instruction', 'Listen to your reading first. If you are happy with it, you can submit your answer.', 'Passage recording review prompt', 'resources/js/Components/Learner/PassageReadingAssessment.vue'),
            $this->line('vivian.processing.checking_recording', 'vivian', 'focused_instruction', 'I am checking your recording now. Please wait a moment while I listen carefully.', 'Assessment recording processing status', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.processing.checking_answer', 'vivian', 'focused_instruction', 'I am checking your answer now. Please wait while I review it.', 'Assessment answer processing status', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.processing.checking_reading', 'vivian', 'focused_instruction', 'I am checking your reading now. This may take a moment, so please wait.', 'Passage reading processing status', 'resources/js/Components/Learner/PassageReadingAssessment.vue'),
            $this->line('vivian.continue.thank_you', 'vivian', 'friendly_encouragement', "Thank you. Let's continue to the next item when you are ready.", 'Assessment transition after saved answer', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.continue.good_effort', 'vivian', 'friendly_encouragement', "Good effort. Let's go to the next one and keep doing our best.", 'Assessment transition after saved answer', 'resources/js/Components/Learner/Task1LetterAssessment.vue'),
            $this->line('vivian.asr.received_01', 'vivian', 'friendly_encouragement', "I heard your answer. Let's keep going.", 'Vivian ASR transcript received cycle 1', 'resources/js/utils/vivianAsrVoiceLines.js'),
            $this->line('vivian.asr.received_02', 'vivian', 'friendly_encouragement', "Your answer came through. Let's continue to the next item.", 'Vivian ASR transcript received cycle 2', 'resources/js/utils/vivianAsrVoiceLines.js'),
            $this->line('vivian.asr.received_03', 'vivian', 'friendly_encouragement', "Thank you. I received your answer, so let's move forward.", 'Vivian ASR transcript received cycle 3', 'resources/js/utils/vivianAsrVoiceLines.js'),
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
        ];
    }

    public function defenseFixtures(): array
    {
        return [];
    }

    public function allSeedLines(): array
    {
        return [
            ...$this->staticLines(),
            ...$this->moduleEchoLines->supportLines(),
            ...$this->moduleEchoLines->moduleLines(),
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
        ?string $synthesisText = null,
    ): array {
        return [
            'line_key' => $lineKey,
            'agent' => $agent,
            'intent' => $intent,
            'context' => $context,
            'source_repo' => str_starts_with($sourceFile, 'ReaDirect-TTS') ? 'ReaDirect-TTS' : 'ReaDirect',
            'source_file' => $sourceFile,
            'text' => $text,
            'synthesis_text' => $synthesisText,
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
