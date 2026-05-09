<?php

namespace App\Services\Agents;

use App\Models\AgentProfile;
use App\Models\LlmInteraction;
use App\Services\AI\OllamaClient;
use App\Support\AgentIdentity;
use Illuminate\Support\Facades\Log;

class MissCielFeedbackService
{
    private const BLOCKED_TERMS = [
        'ai',
        'model',
        'ollama',
        'asr',
        'transcript',
        'database',
        'server',
        'score formula',
        'unlock',
        'module rule',
        'failed',
        'you failed',
        'wrong and bad',
        'disorder',
        'diagnosis',
        'http',
        'www.',
        '```',
        '|',
    ];

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly MissCielScriptedFeedback $scripts,
    ) {}

    public function feedback(array $context): array
    {
        $category = $context['feedback_category'] ?? $this->scripts->categoryFor($context);
        $fallback = $this->scripts->forCategory($category);
        $raw = null;
        $source = 'scripted';
        $fallbackReason = null;
        $latency = null;

        if ($this->ollamaEnabled()) {
            $result = $this->ollama->generate($this->prompt($context, $category));
            $raw = $result['text'];
            $latency = $result['latency_ms'];

            if ($result['error']) {
                $fallbackReason = $result['error'];
            } else {
                $safe = $this->safeText($raw);
                if ($safe['accepted']) {
                    $source = 'ollama';
                    $fallback = $safe['text'];
                    $fallbackReason = null;
                } else {
                    $fallbackReason = $safe['reason'];
                    $this->logRejected($fallbackReason);
                }
            }
        } else {
            $fallbackReason = 'disabled';
        }

        $this->logInteraction($context, $fallback, $source, $fallbackReason, $latency);

        return [
            'agent' => AgentIdentity::MISS_CIEL,
            'display_name' => AgentIdentity::displayName(AgentIdentity::MISS_CIEL),
            'message' => $fallback,
            'source' => $source,
            'fallback_used' => $source !== 'ollama',
            'fallback_reason' => $fallbackReason,
            'latency_ms' => $latency,
        ];
    }

    private function ollamaEnabled(): bool
    {
        return (bool) config('readirect.ollama.enabled')
            && (bool) config('readirect.agent_feedback.miss_ciel_ollama_enabled');
    }

    private function prompt(array $context, string $category): string
    {
        return implode("\n", [
            'You are Miss Ciel, a kind Grade 1 reading coach.',
            'Write one short encouraging message.',
            'Use simple words.',
            'Do not mention AI, model, transcript system, score formula, database, module rules, or technical details.',
            'Do not decide scores.',
            'Do not say the learner passed or failed unless the provided context says so.',
            'Do not unlock modules.',
            'Keep the message under 25 words.',
            'Use a warm teacher tone.',
            'No markdown. No bullet points. No emojis.',
            'Return only the message.',
            'Feedback category: '.$this->clean($category),
            'Module: '.$this->clean($context['module_key'] ?? 'module practice'),
            'Activity: '.$this->clean($context['activity_type'] ?? $context['task_type'] ?? 'reading practice'),
            'Official result: '.(($context['is_correct'] ?? false) ? 'correct' : 'needs practice'),
            'Error type: '.$this->clean($context['error_type'] ?? 'none'),
            'Recommended action: '.$this->clean($context['recommended_action'] ?? 'continue'),
        ]);
    }

    private function safeText(?string $raw): array
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        $text = trim($text, " \t\n\r\0\x0B\"'");

        if ($text === '') {
            return ['accepted' => false, 'reason' => 'empty_output', 'text' => null];
        }

        if (str_word_count($text) > 25 || mb_strlen($text) > 180) {
            return ['accepted' => false, 'reason' => 'too_long', 'text' => null];
        }

        $lower = mb_strtolower($text);
        foreach (self::BLOCKED_TERMS as $term) {
            if (str_contains($lower, $term)) {
                return ['accepted' => false, 'reason' => 'blocked_term', 'text' => null];
            }
        }

        if (preg_match('/https?:\/\/|www\./i', $text)) {
            return ['accepted' => false, 'reason' => 'url_detected', 'text' => null];
        }

        return ['accepted' => true, 'reason' => null, 'text' => $text];
    }

    private function clean(mixed $value): string
    {
        return str((string) $value)
            ->replaceMatches('/[^\pL\pN\s_\-.,!?]/u', '')
            ->limit(90)
            ->toString();
    }

    private function logRejected(string $reason): void
    {
        Log::warning('Miss Ciel Ollama output rejected; scripted fallback used.', ['reason' => $reason]);
    }

    private function logInteraction(array $context, string $message, string $source, ?string $fallbackReason, ?int $latency): void
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
            'provider' => 'ollama',
            'model' => config('readirect.ollama.model', 'qwen3:4b'),
            'sanitized_context' => [
                'agent' => AgentIdentity::MISS_CIEL,
                'module_key' => $context['module_key'] ?? null,
                'activity_type' => $context['activity_type'] ?? $context['task_type'] ?? null,
                'is_correct' => (bool) ($context['is_correct'] ?? false),
                'error_type' => $context['error_type'] ?? null,
                'recommended_action' => $context['recommended_action'] ?? null,
            ],
            'input_summary' => [
                'feedback_category' => $context['feedback_category'] ?? $this->scripts->categoryFor($context),
                'official_scoring_changed' => false,
            ],
            'response_summary' => str($message)->limit(300)->toString(),
            'output_text' => $message,
            'fallback_used' => $source !== 'ollama',
            'safety_status' => $source === 'ollama' ? 'safe' : ($fallbackReason ?? 'scripted'),
            'error_message' => $source === 'ollama' ? null : $fallbackReason,
            'metadata' => [
                'source' => $source,
                'latency_ms' => $latency,
                'official_scoring_changed' => false,
                'official_progression_changed' => false,
            ],
        ]);
    }
}
