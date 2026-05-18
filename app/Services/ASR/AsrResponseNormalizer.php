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
            'gop_enabled' => $aiResponse['gop_enabled'] ?? null,
            'gop_available' => $aiResponse['gop_available'] ?? null,
            'gop_score' => $aiResponse['gop_score'] ?? null,
            'gop_confidence' => $aiResponse['gop_confidence'] ?? null,
            'gop_decision' => $aiResponse['gop_decision'] ?? null,
            'gop_threshold' => $aiResponse['gop_threshold'] ?? null,
            'gop_prompt_type' => $aiResponse['gop_prompt_type'] ?? null,
            'gop_expected_phonemes' => $aiResponse['gop_expected_phonemes'] ?? [],
            'gop_observed_phonemes' => $aiResponse['gop_observed_phonemes'] ?? [],
            'gop_phoneme_scores' => $aiResponse['gop_phoneme_scores'] ?? [],
            'gop_word_scores' => $aiResponse['gop_word_scores'] ?? [],
            'mispronounced_phonemes' => $aiResponse['mispronounced_phonemes'] ?? [],
            'weak_words' => $aiResponse['weak_words'] ?? [],
            'gop_correction_applied' => $aiResponse['gop_correction_applied'] ?? null,
            'gop_error' => $aiResponse['gop_error'] ?? null,
            'dynamic_correction_enabled' => $aiResponse['dynamic_correction_enabled'] ?? null,
            'dynamic_correction_applied' => $aiResponse['dynamic_correction_applied'] ?? null,
            'dynamic_correction_strategy' => $aiResponse['dynamic_correction_strategy'] ?? null,
            'dynamic_correction_sub_strategy' => $aiResponse['dynamic_correction_sub_strategy'] ?? null,
            'dynamic_correction_confidence' => $aiResponse['dynamic_correction_confidence'] ?? null,
            'dynamic_correction_threshold' => $aiResponse['dynamic_correction_threshold'] ?? null,
            'dynamic_spelling_similarity' => $aiResponse['dynamic_spelling_similarity'] ?? null,
            'dynamic_phoneme_similarity' => $aiResponse['dynamic_phoneme_similarity'] ?? null,
            'dynamic_gop_score' => $aiResponse['dynamic_gop_score'] ?? null,
            'dynamic_homophone_match' => $aiResponse['dynamic_homophone_match'] ?? null,
            'dynamic_context_score' => $aiResponse['dynamic_context_score'] ?? null,
            'dynamic_correction_reason' => $aiResponse['dynamic_correction_reason'] ?? null,
            'dynamic_suspicious_fragment' => $aiResponse['dynamic_suspicious_fragment'] ?? null,
            'dynamic_fragment_reasons' => $aiResponse['dynamic_fragment_reasons'] ?? [],
            'dynamic_phoneme_coverage' => $aiResponse['dynamic_phoneme_coverage'] ?? null,
            'asr_spelling_variant_enabled' => $aiResponse['asr_spelling_variant_enabled'] ?? null,
            'asr_spelling_variant_applied' => $aiResponse['asr_spelling_variant_applied'] ?? null,
            'asr_spelling_variant_strategy' => $aiResponse['asr_spelling_variant_strategy'] ?? null,
            'asr_spelling_variant_sub_strategy' => $aiResponse['asr_spelling_variant_sub_strategy'] ?? null,
            'asr_spelling_variant_confidence' => $aiResponse['asr_spelling_variant_confidence'] ?? null,
            'asr_spelling_variant_threshold' => $aiResponse['asr_spelling_variant_threshold'] ?? null,
            'consonant_skeleton_similarity' => $aiResponse['consonant_skeleton_similarity'] ?? null,
            'vowel_tolerant_similarity' => $aiResponse['vowel_tolerant_similarity'] ?? null,
            'expected_phoneme_coverage' => $aiResponse['expected_phoneme_coverage'] ?? null,
            'variant_edit_similarity' => $aiResponse['variant_edit_similarity'] ?? null,
            'variant_reason' => $aiResponse['variant_reason'] ?? null,
            'word_alignment' => $aiResponse['word_alignment'] ?? [],
            'debug_metadata' => $aiResponse['debug_metadata'] ?? null,
        ];
    }

    public function canComplete(array $resolved, array $context = [], bool $allowUncertain = false): bool
    {
        $aiResponse = $resolved['ai_response'] ?? null;
        $normalized = $this->normalize(is_array($aiResponse) ? $aiResponse : null, $resolved['transcript'] ?? null);
        $aiResponseArray = is_array($aiResponse) ? $aiResponse : [];

        if ($normalized['retry_required']) {
            return false;
        }

        if ($this->hasUsableLetterTranscript($normalized['scoring_transcript'], $context, $aiResponseArray)) {
            return true;
        }

        if ($normalized['uncertain'] && ! $allowUncertain) {
            return false;
        }

        if ($this->audioQualityFailed($normalized['audio_quality'], $aiResponseArray)) {
            return false;
        }

        return $this->hasUsableTranscript($normalized['scoring_transcript'], $context, $aiResponseArray);
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
            return mb_strlen($value) > 1
                || mb_strlen($expected) === 1
                || $this->isSpokenLetterAliasForExpectedWord($value, $expected);
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
        foreach ([
            'raw_wer',
            'corrected_wer',
            'raw_cer',
            'corrected_cer',
            'phonetic_similarity_score',
            'dynamic_correction_confidence',
            'dynamic_phoneme_similarity',
            'dynamic_spelling_similarity',
        ] as $key) {
            if (isset($aiResponse[$key]) && is_numeric($aiResponse[$key])) {
                return true;
            }
        }

        return false;
    }

    private function hasUsableLetterTranscript(?string $transcript, array $context, array $aiResponse): bool
    {
        if ($this->effectivePromptType($context, $aiResponse) !== 'letter') {
            return false;
        }

        return $this->hasUsableTranscript($transcript, $context, $aiResponse);
    }

    private function isSpokenLetterAliasForExpectedWord(string $transcript, string $expected): bool
    {
        $letter = mb_strtolower($this->sanitizer->sanitize($transcript));
        $word = mb_strtolower($this->sanitizer->sanitize($expected));

        if (mb_strlen($letter) !== 1 || $word === '') {
            return false;
        }

        $aliases = [
            'a' => ['a', 'aye', 'ay'],
            'b' => ['be', 'bee'],
            'c' => ['see', 'sea'],
            'i' => ['i', 'eye'],
            'o' => ['o', 'oh'],
            'q' => ['cue', 'queue'],
            'r' => ['are'],
            'u' => ['you', 'yew'],
            'x' => ['ex'],
            'y' => ['why'],
        ];

        return in_array($word, $aliases[$letter] ?? [], true);
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
