<?php

namespace App\Services\LLM;

use App\Models\AgentProfile;
use App\Models\LlmInteraction;
use App\Models\LlmPromptTemplate;

class CoachFeedbackLLMService
{
    public function __construct(
        private readonly OpenAIClientService $openAI,
        private readonly LLMOutputSafetyService $safety,
    ) {}

    public function generateFeedback(array $context): string
    {
        $fallback = $this->fallbackText($context);
        $promptTemplate = $this->promptTemplate($context['prompt_key'] ?? null);
        $systemPrompt = $this->systemPrompt($promptTemplate);
        $userPrompt = $this->userPrompt($context);
        $rawOutput = $this->openAI->generateText($systemPrompt, $userPrompt);
        $result = $this->safety->sanitize($rawOutput, $fallback);

        $this->logInteraction($context, $promptTemplate, $result, $rawOutput);

        return $result['text'];
    }

    private function fallbackText(array $context): string
    {
        $template = trim((string) ($context['template_feedback'] ?? 'Great effort! Try this one again.'));
        $retry = trim((string) ($context['retry_instruction'] ?? 'Try again when you are ready.'));

        if (($context['is_correct'] ?? false) || $retry === '') {
            return $template;
        }

        return $template.' '.$retry;
    }

    private function promptTemplate(?string $key): ?LlmPromptTemplate
    {
        $query = LlmPromptTemplate::where('status', 'active');

        if ($key) {
            $template = (clone $query)->where('key', $key)->latest('version')->first();

            if ($template) {
                return $template;
            }
        }

        return $query->where('key', 'coach_feedback_incorrect')->latest('version')->first();
    }

    private function systemPrompt(?LlmPromptTemplate $template): string
    {
        return $template?->template ?: 'You are the Miss Ciel for ReaDirect, a Grade 1 oral reading practice system. Speak kindly and simply to a young learner. Use short sentences. Encourage effort. Do not shame the learner. Do not mention scores unless provided for display. Do not diagnose speech, health, or learning conditions. Do not change official scoring or module decisions. Only explain the given feedback context in child-friendly words.';
    }

    private function userPrompt(array $context): string
    {
        return implode("\n", [
            'Module: '.$this->safeValue($context['module_key'] ?? 'unknown'),
            'Activity: '.$this->safeValue($context['activity_type'] ?? 'unknown'),
            'Expected answer: '.$this->safeValue($context['expected_answer'] ?? ''),
            'Learner response: '.$this->safeValue($context['learner_response'] ?? ''),
            'Correct: '.(($context['is_correct'] ?? false) ? 'yes' : 'no'),
            'Detected error type: '.$this->safeValue($context['error_type'] ?? 'none'),
            'Recommended action: '.$this->safeValue($context['recommended_action'] ?? 'encourage_practice'),
            'Template feedback: '.$this->safeValue($context['template_feedback'] ?? ''),
            'Retry instruction: '.$this->safeValue($context['retry_instruction'] ?? ''),
            'Write one short child-friendly feedback message. Maximum 2 sentences.',
        ]);
    }

    private function logInteraction(array $context, ?LlmPromptTemplate $template, array $result, ?string $rawOutput): void
    {
        $agent = AgentProfile::where('key', AgentProfile::COACH_FEEDBACK)->first();

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
            'sanitized_context' => $this->summary($context),
            'input_summary' => $this->summary($context),
            'response_summary' => str($result['text'])->limit(300)->toString(),
            'output_text' => $result['text'],
            'fallback_used' => $result['fallback_used'],
            'safety_status' => $result['safety_status'],
            'error_message' => $rawOutput === null && ! $result['fallback_used'] ? null : ($result['fallback_used'] ? $result['safety_status'] : null),
            'metadata' => [
                'openai_enabled' => (bool) config('readirect.openai.enabled', false),
                'real_asr' => false,
                'official_scoring_changed' => false,
            ],
        ]);
    }

    private function summary(array $context): array
    {
        return [
            'agent_type' => 'coach_feedback',
            'module_key' => $this->safeValue($context['module_key'] ?? null),
            'activity_type' => $this->safeValue($context['activity_type'] ?? null),
            'is_correct' => (bool) ($context['is_correct'] ?? false),
            'error_type' => $this->safeValue($context['error_type'] ?? null),
            'recommended_action' => $this->safeValue($context['recommended_action'] ?? null),
            'max_words' => (int) ($context['max_words'] ?? 30),
        ];
    }

    private function safeValue(mixed $value): string
    {
        return str((string) $value)
            ->replaceMatches('/[^\pL\pN\s_\-.,!?]/u', '')
            ->limit(140)
            ->toString();
    }
}
