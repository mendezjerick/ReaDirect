<?php

namespace App\Services\Agents;

use App\Models\AgentProfile;
use App\Models\LlmInteraction;
use App\Models\LlmPromptTemplate;
use App\Services\LLM\LLMOutputSafetyService;
use App\Services\LLM\OpenAIClientService;
use App\Services\Scoring\AnswerSimilarityService;

class AgentCommentaryService
{
    public function __construct(
        private readonly OpenAIClientService $openAI,
        private readonly LLMOutputSafetyService $safety,
        private readonly AnswerSimilarityService $similarity,
    ) {
    }

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
        $promptTemplate = $this->promptTemplate();
        $rawOutput = null;

        if ($this->llmAllowed($mode)) {
            $rawOutput = $this->openAI->generateText(
                $promptTemplate?->template ?: $this->systemPrompt(),
                $this->userPrompt($context + [
                    'mode' => $mode,
                    'agent_type' => $agentType,
                    'similarity_label' => $similarityLabel,
                    'error_type' => $errorType,
                ])
            );
        }

        $result = $this->sanitizeForMode($mode, $rawOutput, $fallback, $expected);
        if ($this->llmAllowed($mode)) {
            $this->logInteraction($context, $promptTemplate, $agentType, $mode, $result, $similarityLabel, $errorType);
        }

        return [
            'agent_type' => $agentType,
            'state' => $this->stateFor($mode, $isCorrect, $similarityLabel),
            'message' => $result['text'],
            'fallback_used' => $result['fallback_used'],
            'commentary_mode' => $mode,
            'safety_status' => $result['safety_status'],
            'source' => $result['fallback_used'] ? ($mode === 'assessment_neutral' ? 'local_neutral' : 'template') : 'llm',
            'similarity_label' => $similarityLabel,
            'error_type' => $errorType,
        ];
    }

    private function llmAllowed(string $mode): bool
    {
        return in_array($mode, ['module_coaching', 'evaluator_summary'], true);
    }

    private function sanitizeForMode(string $mode, ?string $output, string $fallback, string $expected): array
    {
        $result = $this->safety->sanitize($output, $fallback);

        if ($mode !== 'assessment_neutral' || $result['fallback_used']) {
            return $result;
        }

        $lower = mb_strtolower($result['text']);
        $expected = $this->similarity->normalize($expected);
        $blocked = ['close', 'hint', 'correct', 'wrong', 'try saying', 'sound'];

        foreach ($blocked as $phrase) {
            if (str_contains($lower, $phrase)) {
                return ['text' => $fallback, 'fallback_used' => true, 'safety_status' => 'assessment_hint_blocked'];
            }
        }

        if ($expected !== '' && str_contains($this->similarity->normalize($result['text']), $expected)) {
            return ['text' => $fallback, 'fallback_used' => true, 'safety_status' => 'assessment_answer_blocked'];
        }

        return $result;
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

    private function promptTemplate(): ?LlmPromptTemplate
    {
        return LlmPromptTemplate::where('key', 'agent_answer_commentary')
            ->where('status', 'active')
            ->latest('version')
            ->first();
    }

    private function systemPrompt(): string
    {
        return 'You are a ReaDirect agent speaking to a Grade 1 learner. Your job is to respond kindly after the learner gives an answer. You must use only the provided result context. You do not decide scores or correctness. You do not change the system decision. If the mode is assessment_neutral, do not give hints, corrections, closeness, or correct answers. If the mode is module_coaching, you may explain what was close, what to try next, and encourage retry. Keep the message short, friendly, and appropriate for a young learner. Do not shame the learner. Do not diagnose conditions. Do not mention internal scoring rules.';
    }

    private function userPrompt(array $context): string
    {
        return implode("\n", [
            'Mode: '.($context['mode'] ?? 'module_coaching'),
            'Agent: '.($context['agent_type'] ?? AgentProfile::COACH_FEEDBACK),
            'Task or activity: '.($context['activity_type'] ?? $context['task_type'] ?? 'unknown'),
            'Expected answer: '.($context['expected_answer'] ?? ''),
            'Learner answer: '.($context['learner_answer'] ?? $context['learner_transcript'] ?? ''),
            'System correctness: '.(($context['is_correct'] ?? false) ? 'correct' : 'not correct'),
            'System score: '.($context['score'] ?? '0'),
            'Error type: '.($context['error_type'] ?? 'none'),
            'Similarity label: '.($context['similarity_label'] ?? 'unknown'),
            'Recommended action: '.($context['recommended_action'] ?? 'continue'),
            'Template feedback: '.($context['template_feedback'] ?? ''),
            'Write one short message for the learner. Maximum 2 sentences.',
        ]);
    }

    private function logInteraction(array $context, ?LlmPromptTemplate $template, string $agentType, string $mode, array $result, string $similarityLabel, string $errorType): void
    {
        $agent = AgentProfile::where('key', $agentType === 'evaluator' ? AgentProfile::EVALUATOR_RECOMMENDATION : $agentType)->first();

        if (! $agent) {
            return;
        }

        LlmInteraction::create([
            'learner_id' => $context['learner_id'] ?? null,
            'agent_profile_id' => $agent->id,
            'source_type' => $context['source_type'] ?? null,
            'source_id' => $context['source_id'] ?? null,
            'llm_prompt_template_id' => $template?->id,
            'provider' => 'openai',
            'model' => config('readirect.openai.model', 'gpt-4.1-mini'),
            'sanitized_context' => [
                'mode' => $mode,
                'agent_type' => $agentType,
                'activity_type' => $context['activity_type'] ?? $context['task_type'] ?? null,
                'is_correct' => (bool) ($context['is_correct'] ?? false),
                'similarity_label' => $similarityLabel,
                'error_type' => $errorType,
            ],
            'input_summary' => [
                'mode' => $mode,
                'recommended_action' => $context['recommended_action'] ?? null,
            ],
            'response_summary' => str($result['text'])->limit(300)->toString(),
            'output_text' => $result['text'],
            'fallback_used' => $result['fallback_used'],
            'safety_status' => $result['safety_status'],
            'error_message' => $result['fallback_used'] ? $result['safety_status'] : null,
            'metadata' => [
                'commentary_mode' => $mode,
                'official_scoring_changed' => false,
            ],
        ]);
    }
}
