<?php

namespace App\Services\ASR;

use App\Services\STT\TranscriptSanitizer;

class AsrResponseNormalizer
{
    public function __construct(private readonly TranscriptSanitizer $sanitizer)
    {
    }

    public function normalize(?array $aiResponse, ?string $fallbackTranscript = null): array
    {
        $aiResponse ??= [];
        $scoringTranscript = $this->firstTranscript($aiResponse, ['corrected_transcript', 'transcript', 'raw_transcript', 'normalized_transcript'], $fallbackTranscript);
        $displayTranscript = $this->firstTranscript($aiResponse, ['displayed_transcript', 'corrected_transcript', 'transcript', 'raw_transcript', 'normalized_transcript'], $scoringTranscript);
        $rawTranscript = $this->firstTranscript($aiResponse, ['raw_transcript'], $fallbackTranscript);

        return [
            'scoring_transcript' => $scoringTranscript,
            'display_transcript' => $displayTranscript,
            'debug_transcript' => $rawTranscript,
            'expected_text' => $aiResponse['expected_text'] ?? null,
            'prompt_type' => $aiResponse['prompt_type'] ?? null,
            'accepted' => $aiResponse['accepted'] ?? null,
            'retry_required' => (bool) ($aiResponse['retry_required'] ?? false),
            'uncertain' => (bool) ($aiResponse['uncertain'] ?? false),
            'uncertainty_reasons' => $aiResponse['uncertainty_reasons'] ?? [],
            'raw_wer' => $aiResponse['raw_wer'] ?? null,
            'corrected_wer' => $aiResponse['corrected_wer'] ?? null,
            'raw_cer' => $aiResponse['raw_cer'] ?? null,
            'corrected_cer' => $aiResponse['corrected_cer'] ?? null,
            'phonetic_similarity_score' => $aiResponse['phonetic_similarity_score'] ?? null,
            'correction_strategy_used' => $aiResponse['correction_strategy_used'] ?? null,
            'model_used' => $aiResponse['model_used'] ?? null,
            'model_family' => $aiResponse['model_family'] ?? null,
            'asr_route' => $aiResponse['asr_route'] ?? null,
            'audio_quality' => $aiResponse['audio_quality'] ?? null,
            'pause_metrics' => $aiResponse['pause_metrics'] ?? null,
            'debug_metadata' => $aiResponse['debug_metadata'] ?? null,
        ];
    }

    public function canComplete(array $resolved, array $context = [], bool $allowUncertain = false): bool
    {
        $aiResponse = $resolved['ai_response'] ?? null;
        $normalized = $this->normalize(is_array($aiResponse) ? $aiResponse : null, $resolved['transcript'] ?? null);

        if ($normalized['retry_required']) {
            return false;
        }

        if ($normalized['uncertain'] && ! $allowUncertain) {
            return false;
        }

        if ($this->audioQualityFailed($normalized['audio_quality'], is_array($aiResponse) ? $aiResponse : [])) {
            return false;
        }

        return $this->hasUsableTranscript($normalized['scoring_transcript'], $context, is_array($aiResponse) ? $aiResponse : []);
    }

    public function hasUsableTranscript(?string $transcript, array $context = [], array $aiResponse = []): bool
    {
        $value = $this->sanitizer->sanitize($transcript);

        if ($value === '') {
            return false;
        }

        if (in_array(mb_strtolower($value), ['silence', 'silent', 'noise', 'background noise', 'inaudible'], true)) {
            return false;
        }

        $promptType = $this->effectivePromptType($context, $aiResponse);
        $expected = $this->sanitizer->sanitize($context['expected_text'] ?? $aiResponse['expected_text'] ?? '');
        $wordCount = str_word_count($value);

        if ($promptType === 'letter') {
            return true;
        }

        if (in_array($promptType, ['word', 'rhyme'], true)) {
            return mb_strlen($value) > 1 || mb_strlen($expected) === 1;
        }

        if ($promptType === 'passage' || $promptType === 'paragraph') {
            return $wordCount >= 3 || $this->hasAsrEvidence($aiResponse);
        }

        if ($promptType === 'sentence') {
            return $wordCount >= 2 || $this->hasAsrEvidence($aiResponse);
        }

        return true;
    }

    public function completionFailureMessage(array $resolved, array $context = []): string
    {
        $aiResponse = is_array($resolved['ai_response'] ?? null) ? $resolved['ai_response'] : [];

        if (($aiResponse['retry_required'] ?? false) === true && trim((string) ($aiResponse['learner_retry_message'] ?? '')) !== '') {
            return (string) $aiResponse['learner_retry_message'];
        }

        if (($aiResponse['retry_required'] ?? false) === true) {
            return 'We could not hear enough clear speech. Please record again.';
        }

        if (($aiResponse['uncertain'] ?? false) === true) {
            return 'We are not sure enough about that recording. Please try again.';
        }

        if ($this->audioQualityFailed($aiResponse['audio_quality'] ?? null, $aiResponse)) {
            return 'That recording was not clear enough to score. Please try again.';
        }

        return 'Let us answer this first.';
    }

    private function firstTranscript(array $payload, array $keys, ?string $fallback = null): string
    {
        foreach ($keys as $key) {
            $value = $this->sanitizer->sanitize($payload[$key] ?? null);

            if ($value !== '') {
                return $value;
            }
        }

        return $this->sanitizer->sanitize($fallback);
    }

    private function audioQualityFailed(mixed $audioQuality, array $aiResponse): bool
    {
        if (($aiResponse['quality_gate_failed'] ?? false) === true) {
            return true;
        }

        if (! is_array($audioQuality)) {
            return false;
        }

        foreach (['passed', 'is_acceptable', 'acceptable'] as $key) {
            if (array_key_exists($key, $audioQuality) && $audioQuality[$key] === false) {
                return true;
            }
        }

        $label = mb_strtolower((string) ($audioQuality['quality_label'] ?? $audioQuality['label'] ?? $audioQuality['status'] ?? ''));

        return in_array($label, ['bad', 'poor', 'failed', 'silence', 'too_short'], true);
    }

    private function hasAsrEvidence(array $aiResponse): bool
    {
        foreach (['raw_wer', 'corrected_wer', 'raw_cer', 'corrected_cer', 'phonetic_similarity_score'] as $key) {
            if (isset($aiResponse[$key]) && is_numeric($aiResponse[$key])) {
                return true;
            }
        }

        return false;
    }

    private function effectivePromptType(array $context, array $aiResponse): string
    {
        $taskType = mb_strtolower((string) ($context['task_type'] ?? ''));
        $activityType = mb_strtolower((string) ($context['activity_type'] ?? ''));
        $expected = $this->sanitizer->sanitize($context['expected_text'] ?? $aiResponse['expected_text'] ?? '');

        if (str_contains($taskType, '2b') && $expected !== '' && ! str_contains($expected, ' ')) {
            return 'word';
        }

        if (str_contains($taskType, 'passage') || str_contains($activityType, 'passage')) {
            return 'passage';
        }

        if (str_contains($taskType, 'paragraph') || str_contains($activityType, 'paragraph')) {
            return 'paragraph';
        }

        if (str_contains($taskType, 'sentence') || str_contains($activityType, 'sentence')) {
            return 'sentence';
        }

        if (str_contains($taskType, 'letter') || str_contains($activityType, 'letter') || mb_strlen($expected) === 1) {
            return 'letter';
        }

        if (str_contains($taskType, 'rhyme') || str_contains($activityType, 'rhyme')) {
            return 'rhyme';
        }

        return (string) ($aiResponse['prompt_type'] ?? (str_contains($expected, ' ') ? 'sentence' : 'word'));
    }
}
