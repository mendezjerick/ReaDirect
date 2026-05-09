<?php

namespace App\Services\Agents;

use App\Models\AgentProfile;
use App\Services\Scoring\AnswerSimilarityService;
use App\Support\AgentIdentity;

class AgentCommentaryService
{
    public function __construct(
        private readonly MissCielFeedbackService $missCiel,
        private readonly AnswerSimilarityService $similarity,
    ) {}

    public function generateCommentary(array $context): array
    {
        $mode = $context['mode'] ?? 'module_coaching';
        $agentType = $context['agent_type'] ?? $this->agentForMode($mode);
        $expected = (string) ($context['expected_answer'] ?? '');
        $answer = (string) ($context['learner_answer'] ?? $context['learner_transcript'] ?? '');
        $isCorrect = (bool) ($context['is_correct'] ?? false);
        $similarityLabel = $context['similarity_label'] ?? $this->similarity->classifySimilarity($expected, $answer);
        $errorType = $context['error_type'] ?? $this->similarity->detectErrorType($expected, $answer, $isCorrect);
        $fallback = $this->fallbackMessage($mode, $agentType, $isCorrect, $errorType, $similarityLabel, $context);

        if ($mode === 'module_coaching') {
            $feedback = $this->missCiel->feedback($context + [
                'agent_type' => AgentIdentity::MISS_CIEL,
                'similarity_label' => $similarityLabel,
                'error_type' => $errorType,
                'template_feedback' => $fallback,
            ]);

            return [
                'agent_type' => AgentProfile::COACH_FEEDBACK,
                'display_name' => $feedback['display_name'],
                'state' => $this->stateFor($mode, $isCorrect, $similarityLabel),
                'message' => $feedback['message'],
                'fallback_used' => $feedback['fallback_used'],
                'commentary_mode' => $mode,
                'safety_status' => $feedback['fallback_used'] ? ($feedback['fallback_reason'] ?? 'scripted') : 'safe',
                'source' => $feedback['source'],
                'similarity_label' => $similarityLabel,
                'error_type' => $errorType,
            ];
        }

        return [
            'agent_type' => $agentType,
            'display_name' => $agentType === AgentProfile::ASSESSMENT
                ? AgentIdentity::displayName(AgentIdentity::MISS_VIVIAN)
                : AgentIdentity::displayName(AgentIdentity::MISS_ESTELLE),
            'state' => $this->stateFor($mode, $isCorrect, $similarityLabel),
            'message' => $fallback,
            'fallback_used' => true,
            'commentary_mode' => $mode,
            'safety_status' => 'fixed_script',
            'source' => $mode === 'assessment_neutral' ? 'miss_vivian_script' : 'miss_estelle_script',
            'similarity_label' => $similarityLabel,
            'error_type' => $errorType,
        ];
    }

    private function fallbackMessage(string $mode, string $agentType, bool $isCorrect, string $errorType, string $similarityLabel, array $context): string
    {
        if ($mode === 'assessment_neutral') {
            return match (($context['attempt_number'] ?? 0) % 3) {
                1 => 'Good effort. Let us go to the next one.',
                2 => 'I heard your answer. Let us keep going.',
                default => 'Thank you. Let us continue.',
            };
        }

        if ($mode === 'evaluator_summary') {
            return match ($context['recommended_action'] ?? '') {
                'move_to_module_2' => 'You are moving to Module 2. Now we will practice reading words.',
                'move_to_module_3' => 'You are moving to Module 3. Now we will practice sentences.',
                'repeat_module' => 'Good effort. We will practice this module again.',
                'extra_drills' => 'We will do extra sound practice to get stronger.',
                'proceed_to_reassessment' => 'Great work. You are ready for the next reading check.',
                'no_module_needed' => 'Great work. Your reading path is complete for now.',
                default => $context['template_feedback'] ?? 'Your next step is ready.',
            };
        }

        if ($isCorrect) {
            return 'Great job! You got it.';
        }

        if ($similarityLabel === 'very_close' && $errorType === 'final_sound_error') {
            return 'Good try! That was very close. Listen to the ending sound and try again.';
        }

        return match ($errorType) {
            'final_sound_error' => 'Good try! Listen to the ending sound and try again.',
            'initial_sound_error' => 'Good effort! Let us listen to the first sound.',
            'vowel_error' => 'Nice try! Let us listen to the middle sound.',
            'skipped_word' => 'Good effort! Let us read each word from left to right.',
            'blank' => 'Let us try this one first.',
            default => match ($similarityLabel) {
                'very_close' => 'Good try! That was very close. Let us fix one small sound.',
                'close', 'somewhat_close' => 'Great effort! You are getting close. Let us try it slowly.',
                default => 'Good effort! Let us listen again and try one more time.',
            },
        };
    }

    private function stateFor(string $mode, bool $isCorrect, string $similarityLabel): string
    {
        if ($mode === 'assessment_neutral') {
            return 'speaking';
        }

        if ($mode === 'evaluator_summary') {
            return 'pointing';
        }

        if ($isCorrect) {
            return 'happy';
        }

        return in_array($similarityLabel, ['very_close', 'close', 'somewhat_close'], true) ? 'encouraging' : 'thinking';
    }

    private function agentForMode(string $mode): string
    {
        return match ($mode) {
            'assessment_neutral' => AgentProfile::ASSESSMENT,
            'evaluator_summary' => 'evaluator',
            default => AgentProfile::COACH_FEEDBACK,
        };
    }
}
