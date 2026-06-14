<?php

namespace App\Services\Ciel;

use Illuminate\Support\Facades\Http;
use Throwable;

class CielTutorAgentClient
{
    private const ANIMATIONS = [
        'c-advise',
        'c-clap',
        'c-confused',
        'c-congrats',
        'c-happy',
        'c-idle',
        'c-talk',
        'c-thinking-1',
        'c-thinking-2',
        'c-thinking-3',
    ];

    public function decide(array $event): array
    {
        $payload = $this->payload($event);

        if ((bool) config('readirect.ciel.service_enabled', true)) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('readirect.ciel.connect_timeout_seconds', 1))
                    ->timeout((int) config('readirect.ciel.timeout_seconds', 3))
                    ->post($this->endpoint(), $payload);

                if ($response->successful()) {
                    $decision = $response->json('ciel_agent');
                    if (is_array($decision) && $this->validDecision($decision)) {
                        return $decision + [
                            'decision_source' => 'readirect_ia',
                            'official_progression_changed' => false,
                        ];
                    }
                }
            } catch (Throwable) {
                // Learner scoring must continue when the tutoring service is unavailable.
            }
        }

        return $this->fallbackDecision($payload);
    }

    private function payload(array $event): array
    {
        $ai = is_array($event['ai_response'] ?? null) ? $event['ai_response'] : [];

        return [
            'learner_id' => $event['learner_id'] ?? 0,
            'session_id' => (string) ($event['session_id'] ?? 'module-session'),
            'module_type' => (string) ($event['module_type'] ?? $event['context'] ?? 'module_practice'),
            'expected' => (string) ($event['expected'] ?? $event['target_text'] ?? ''),
            'transcript' => (string) ($event['transcript'] ?? ''),
            'is_correct' => (bool) ($event['is_correct'] ?? false),
            'attempt' => max(1, (int) ($event['attempt'] ?? $event['attempt_number'] ?? 1)),
            'asr_confidence' => $this->nullableFloat($event['asr_confidence'] ?? null),
            'gop_score' => $this->nullableFloat($event['gop_score'] ?? $ai['gop_score'] ?? $ai['overall_gop_score'] ?? null),
            'phoneme_errors' => $this->arrayValue($event['phoneme_errors'] ?? $ai['phoneme_errors'] ?? []),
            'error_type' => $event['error_type'] ?? $ai['error_type'] ?? null,
            'target_phoneme' => $event['target_phoneme'] ?? null,
            'activity_id' => $event['activity_id'] ?? null,
            'is_final_assessment_completion' => (bool) ($event['is_final_assessment_completion'] ?? false),
            'audio_duration_seconds' => $this->nullableFloat($event['audio_duration_seconds'] ?? null),
            'audio_too_short' => (bool) ($event['audio_too_short'] ?? false),
            'retry_required' => (bool) ($event['retry_required'] ?? $ai['retry_required'] ?? false),
            'uncertain' => (bool) ($event['uncertain'] ?? $ai['uncertain'] ?? false),
        ];
    }

    private function validDecision(array $decision): bool
    {
        $animation = (string) ($decision['animation'] ?? '');
        $mode = (string) ($decision['mode'] ?? '');

        if (($decision['agent'] ?? null) !== 'ciel' || ! in_array($animation, self::ANIMATIONS, true)) {
            return false;
        }

        return ! ($animation === 'c-congrats' && $mode !== 'final_assessment_completion');
    }

    private function fallbackDecision(array $payload): array
    {
        $correct = (bool) $payload['is_correct'];
        $final = (bool) $payload['is_final_assessment_completion'];
        $attempt = (int) $payload['attempt'];
        $expected = strtoupper(trim((string) $payload['expected']));
        $transcript = strtoupper(trim((string) $payload['transcript']));
        $errorType = strtolower(trim((string) ($payload['error_type'] ?? '')));
        $lowConfidence = ! $correct && (
            $transcript === ''
            || $payload['audio_too_short']
            || $payload['retry_required']
            || $payload['uncertain']
            || ($payload['asr_confidence'] !== null && $payload['asr_confidence'] < 0.5)
            || in_array($errorType, ['unclear_audio', 'unclear_asr', 'audio_too_unclear'], true)
        );

        if ($final) {
            return $this->decision($payload, 'final_assessment_completion', 'c-congrats', 'celebratory', 'You completed the final assessment. Well done. Let\'s see your results.', 'show_results');
        }

        if ($correct) {
            $message = $expected !== '' ? "Good job. You read {$expected} correctly." : 'Good job. That is correct.';

            return $this->decision($payload, 'correct_praise', 'c-clap', 'positive_praise', $message, 'continue');
        }

        if ($lowConfidence) {
            return $this->decision($payload, 'hint', 'c-thinking-1', 'patient_guidance', 'I could not hear that clearly. Speak clearly and try recording again.', 'retry_recording', 'low_confidence_audio');
        }

        if (in_array($errorType, ['word_deletion', 'word_insertion', 'word_boundary_error', 'omission', 'insertion'], true)) {
            $mode = $attempt >= 2 ? 'focus_teach' : 'slow_practice';
            $message = $expected !== '' ? "Read slowly, one part at a time: {$expected}." : 'Read slowly, one part at a time.';

            return $this->decision($payload, $mode, 'c-advise', 'gentle_correction', $message, $mode === 'focus_teach' ? 'listen_then_retry' : 'retry_slowly', $errorType);
        }

        if ($errorType === 'letter_confusion' && $expected !== '' && $transcript !== '') {
            $message = "{$expected} and {$transcript} sound close. Let's listen carefully: {$expected}.";
        } elseif (in_array($errorType, ['vowel_confusion', 'vowel_error', 'middle_sound_error'], true)) {
            $message = $expected !== '' ? 'Listen to the middle sound in '.strtolower($expected).'.' : 'Listen carefully to the middle sound.';
        } elseif (in_array($errorType, ['final_sound_missing', 'final_sound_error'], true)) {
            $message = $expected !== '' ? 'Good start. Let\'s finish '.strtolower($expected).' with the last sound.' : 'Good start. Let\'s finish the word with the last sound.';
        } else {
            $message = $attempt >= 2
                ? ($expected !== '' ? "Let's practice {$expected}. Listen carefully. {$expected}." : 'Let\'s practice this together. Listen carefully, then repeat.')
                : ($expected !== '' ? "Good try. Read {$expected} once more." : 'Good try. Please try once more.');
        }

        if ($attempt >= 2) {
            return $this->decision($payload, 'focus_teach', 'c-advise', 'gentle_correction', $message, 'listen_then_retry', $errorType ?: 'generic_error');
        }

        $animation = in_array($errorType, ['vowel_confusion', 'vowel_error', 'middle_sound_error', 'final_sound_missing', 'final_sound_error'], true)
            ? 'c-advise'
            : 'c-confused';

        return $this->decision($payload, 'soft_retry', $animation, 'encouraging_retry', $message, 'retry', $errorType ?: null);
    }

    private function decision(
        array $payload,
        string $mode,
        string $animation,
        string $emotion,
        string $message,
        string $nextAction,
        ?string $teachingFocus = null,
    ): array {
        $focus = $mode === 'focus_teach';

        return [
            'agent' => 'ciel',
            'mode' => $mode,
            'animation' => $animation,
            'emotion' => $emotion,
            'message' => $message,
            'display_target' => strtoupper(trim((string) $payload['expected'])),
            'next_action' => $nextAction,
            'lock_interaction' => $focus,
            'repeat_after_agent' => $focus,
            'teaching_focus' => $teachingFocus,
            'focus_mode' => [
                'enabled' => $focus,
                'layout' => $focus ? 'blank_screen' : 'standard',
                'target_position' => $focus ? 'center' : 'default',
                'agent_position' => $focus ? 'bottom' : 'default',
                'target_size' => $focus ? 'large' : 'normal',
            ],
            'memory_update' => [
                'error_key' => null,
                'count_increment' => 0,
                'current_count' => 0,
                'learner_id' => (string) $payload['learner_id'],
                'session_id' => (string) $payload['session_id'],
            ],
            'reason_codes' => [strtoupper($teachingFocus ?: ($mode === 'correct_praise' ? 'correct_response' : $mode))],
            'official_progression_changed' => false,
            'decision_source' => 'laravel_deterministic_fallback',
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('readirect.ciel.base_url', 'http://127.0.0.1:8003'), '/')
            .'/'
            .ltrim((string) config('readirect.ciel.decide_endpoint', '/ia/ciel/decide'), '/');
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
