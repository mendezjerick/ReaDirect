<?php

namespace App\Services\Agents;

class MissCielScriptedFeedback
{
    private const MESSAGES = [
        'correct' => 'Great job! You said that clearly, and I can hear that you are getting more confident.',
        'incorrect' => 'That is okay, let us try that one more time. This one can be tricky, but we can slow it down together.',
        'partial' => 'No worries, you were close. Let us listen carefully, then say it again a little slower.',
        'retry' => 'When you are ready, say it clearly one more time. We will go slowly, so you do not need to rush.',
        'encouragement' => 'You can do it. Look at the word first, breathe softly, and then say it when you are ready.',
        'extra_drills' => 'These practice sounds will help your reading get stronger. We will do them together, one careful step at a time.',
        'mastery_pass' => 'Great job! You got that part, and you read it with a nice clear voice.',
        'mastery_repeat' => 'That is okay. We will keep practicing this part so it feels easier and more comfortable next time.',
        'mastery_return_previous' => 'We will practice an earlier skill again. That will help your reading feel stronger and more steady.',
        'module_start' => 'Hi, I am Miss Ciel. I will read with you today, and we will take each word slowly together.',
        'module_continue' => 'Ready? Let us keep practicing together. Go slowly, and just try your best.',
        'module_complete' => 'Nice work finishing this module practice. Keep your voice clear, and let us move forward carefully.',
        'final_reassessment_ready' => 'You worked hard in your modules. Stay calm, listen carefully, and do your best on your final reading check.',
        'goodbye' => 'See you next time. Keep practicing, keep using your clear voice, and remember that every step helps.',
        'generic_error' => 'Good effort. Let us slow down and try again when you are ready.',
    ];

    public function forCategory(string $category): string
    {
        return self::MESSAGES[$category] ?? self::MESSAGES['generic_error'];
    }

    public function categoryFor(array $context): string
    {
        $action = (string) ($context['recommended_action'] ?? '');
        $errorType = (string) ($context['error_type'] ?? '');

        if (($context['is_correct'] ?? false) === true) {
            return 'correct';
        }

        if (in_array(($context['similarity_label'] ?? null), ['very_close', 'close', 'somewhat_close'], true)) {
            return 'partial';
        }

        return match ($action) {
            'extra_drills' => 'extra_drills',
            'proceed_to_reassessment' => 'final_reassessment_ready',
            'mastery_pass', 'move_to_module_2', 'move_to_module_3' => 'mastery_pass',
            'repeat_module' => 'mastery_repeat',
            'return_previous_module' => 'mastery_return_previous',
            'module_complete' => 'module_complete',
            'goodbye' => 'goodbye',
            default => in_array($errorType, ['blank', 'retry'], true) ? 'retry' : 'incorrect',
        };
    }
}
