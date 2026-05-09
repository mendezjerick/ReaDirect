<?php

namespace App\Services\Agents;

class MissCielScriptedFeedback
{
    private const MESSAGES = [
        'correct' => 'Great job! You answered that well. Keep going!',
        'incorrect' => 'Good try! Let us practice it again slowly. You can do it!',
        'partial' => 'Good try! You are very close. Let us fix one small sound.',
        'retry' => 'Let us try one more time. Speak clearly and do your best.',
        'encouragement' => 'Keep trying. Every practice step helps you get stronger.',
        'extra_drills' => 'These practice sounds will help you get stronger. Let us do them together.',
        'mastery_pass' => 'Great work! You are ready for the next step.',
        'mastery_repeat' => 'That is okay. More practice will help you become better.',
        'mastery_return_previous' => 'We will practice an earlier skill again so you can feel stronger.',
        'module_start' => 'Hi! I am Miss Ciel. I will help you practice reading.',
        'module_continue' => 'Let us keep practicing one step at a time.',
        'module_complete' => 'Nice work finishing this module practice.',
        'final_reassessment_ready' => 'You worked hard in your modules. Do your best on your final reading check!',
        'goodbye' => 'See you next time! Keep practicing and keep doing your best.',
        'generic_error' => 'Good effort! Let us try again when you are ready.',
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
