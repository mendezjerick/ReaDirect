<?php

namespace App\Services\AI;

use App\Models\AudioFile;
use App\Services\ASR\AsrResponseNormalizer;
use App\Services\DeveloperReinforcementModeService;
use App\Services\STT\AudioTranscriptionService;
use App\Services\STT\TranscriptSanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIAnalysisResolver
{
    public function __construct(
        private readonly ReadirectAIService $ai,
        private readonly AudioTranscriptionService $transcription,
        private readonly TranscriptSanitizer $sanitizer,
        private readonly AsrResponseNormalizer $normalizer,
        private readonly DeveloperReinforcementModeService $developerReinforcementMode
    ) {
    }

    public function resolve(?string $manualTranscript, ?AudioFile $audioFile, array $context = [], array $sttOptions = []): array
    {
        $manual = $this->sanitizer->sanitize($manualTranscript);
        $aiResponse = null;

        if ($manual !== '' && (bool) config('readirect_ai.fallback.use_manual_transcript_if_available', true)) {
            return $this->resolved($manual, 'manual', null, null);
        }

        if ($this->shouldUseAi()) {
            $aiResponse = $this->callAi($manual, $audioFile, $context);

            if (($aiResponse['ok'] ?? false) && $this->extractTranscript($aiResponse, $context) !== '') {
                $this->storeAudioAiFields($audioFile, $aiResponse);

                return $this->resolved(
                    transcript: $this->extractTranscript($aiResponse, $context),
                    source: 'ai_asr',
                    confidence: $aiResponse['confidence'] ?? null,
                    aiResponse: $aiResponse,
                );
            }

            if (($aiResponse['ok'] ?? false) && ($aiResponse['retry_required'] ?? false) === true) {
                $this->storeAudioAiFields($audioFile, $aiResponse);

                return $this->resolved(
                    transcript: '',
                    source: 'ai_asr',
                    confidence: $aiResponse['confidence'] ?? null,
                    aiResponse: $aiResponse,
                );
            }
        }

        if ($audioFile && (bool) config('readirect_ai.fallback.use_existing_stt_if_ai_offline', true)) {
            $result = $this->transcription->transcribeAudioFile($audioFile, $sttOptions);

            if ($result->hasTranscript()) {
                return $this->resolved($result->transcript ?? '', 'stt_auto', $result->confidence, $aiResponse, $result);
            }
        }

        return $this->resolved('', 'manual', null, $aiResponse);
    }

    public function responseFields(?array $aiResponse): array
    {
        if (! $aiResponse) {
            return [];
        }

        return [
            'ai_transcript' => $aiResponse['raw_transcript'] ?? $aiResponse['transcript'] ?? null,
            'ai_normalized_transcript' => $aiResponse['corrected_transcript'] ?? $aiResponse['normalized_transcript'] ?? null,
            'ai_similarity_label' => $aiResponse['similarity_label'] ?? null,
            'ai_character_similarity' => $this->nullableFloat($aiResponse['character_similarity'] ?? null),
            'ai_token_similarity' => $this->nullableFloat($aiResponse['token_similarity'] ?? null),
            'ai_expected_phonemes' => $aiResponse['expected_phonemes'] ?? null,
            'ai_actual_phonemes' => $aiResponse['observed_phonemes'] ?? $aiResponse['actual_phonemes'] ?? null,
            'ai_phoneme_similarity' => $this->nullableFloat($aiResponse['phonetic_similarity_score'] ?? $aiResponse['phoneme_similarity'] ?? null),
            'ai_error_type' => $aiResponse['error_type'] ?? null,
            'ai_error_position' => $aiResponse['error_position'] ?? null,
            'ai_feedback_hint' => $aiResponse['feedback_hint'] ?? null,
            'ai_coach_hint_key' => $aiResponse['coach_hint_key'] ?? null,
            'ai_skill_signal' => $aiResponse['skill_signal'] ?? null,
            'ai_target_phoneme' => $aiResponse['target_phoneme'] ?? null,
            'ai_recommended_practice_focus' => $aiResponse['recommended_practice_focus'] ?? null,
            'ai_response' => $aiResponse,
            'ai_analyzed_at' => now(),
        ];
    }

    public function acceptedForShortPrompt(?array $aiResponse): bool
    {
        if (! $aiResponse || ($aiResponse['accepted'] ?? null) !== true) {
            return false;
        }

        $promptType = (string) ($aiResponse['prompt_type'] ?? '');

        if ($promptType === '' && str_contains(trim((string) ($aiResponse['expected_text'] ?? '')), ' ')) {
            return false;
        }

        return $promptType === '' || in_array($promptType, ['letter', 'word'], true);
    }

    public function canComplete(array $resolved, array $context = [], bool $allowUncertain = false): bool
    {
        return $this->normalizer->canComplete($resolved, $context, $allowUncertain);
    }

    public function completionFailureMessage(array $resolved, array $context = []): string
    {
        return $this->normalizer->completionFailureMessage($resolved, $context);
    }

    private function shouldUseAi(): bool
    {
        return (bool) config('readirect_ai.enabled');
    }

    private function callAi(string $manual, ?AudioFile $audioFile, array $context): array
    {
        $payload = $this->payload($context);

        if ($audioFile && trim((string) ($payload['expected_text'] ?? '')) === '') {
            Log::warning('ASR request is missing expected_text; expected-centric scoring will be disabled in AI.', [
                'activity_type' => $payload['activity_type'] ?? null,
                'task_type' => $payload['task_type'] ?? null,
                'item_id' => $payload['item_id'] ?? null,
                'attempt_id' => $payload['attempt_id'] ?? null,
            ]);
        }

        if ($audioFile) {
            $payload['audio_path'] = $this->absoluteAudioPath($audioFile);

            return $this->ai->analyzeAudio($payload);
        }

        if ($manual !== '') {
            $payload['actual_text'] = $manual;

            return $this->ai->analyzeText($payload);
        }

        return [
            'ok' => false,
            'error' => 'missing_audio_or_text',
            'warnings' => ['No audio file or manual transcript was available for AI analysis.'],
        ];
    }

    private function payload(array $context): array
    {
        $promptType = $context['prompt_type'] ?? $this->inferPromptType($context);

        return [
            'expected_text' => $context['expected_text'] ?? null,
            'prompt_type' => $promptType,
            'accepted_answers' => array_values($context['accepted_answers'] ?? []),
            'prompt_id' => $context['prompt_id'] ?? null,
            'module_key' => $context['module_key'] ?? null,
            'module_type' => $context['module_type'] ?? $context['module_key'] ?? null,
            'activity_type' => $context['activity_type'] ?? null,
            'assessment_type' => $context['assessment_type'] ?? null,
            'task_type' => $context['task_type'] ?? null,
            'item_id' => $context['item_id'] ?? null,
            'learner_id' => $context['learner_id'] ?? null,
            'attempt_id' => $context['attempt_id'] ?? null,
            'current_scoring_context' => $context['current_scoring_context'] ?? [],
            'learner_history' => array_values($context['learner_history'] ?? []),
            'candidate_items' => array_values($context['candidate_items'] ?? []),
            'content_metadata' => $context['content_metadata'] ?? [],
            'debug' => (bool) ($context['debug'] ?? false),
            ...$this->developerReinforcementMode->payloadFor(auth()->user()),
        ];
    }

    private function absoluteAudioPath(AudioFile $audioFile): string
    {
        $disk = $audioFile->disk ?: 'local';
        $path = $audioFile->file_path ?: $audioFile->path;

        return Storage::disk($disk)->path($path);
    }

    private function extractTranscript(array $aiResponse, array $context = []): string
    {
        return $this->normalizer->normalize($aiResponse)['scoring_transcript'];
    }

    private function extractDisplayedTranscript(array $aiResponse, string $scoringTranscript): string
    {
        return $this->normalizer->normalize($aiResponse, $scoringTranscript)['display_transcript'];
    }

    private function resolved(string $transcript, string $source, mixed $confidence, ?array $aiResponse, mixed $sttResult = null): array
    {
        return [
            'transcript' => $transcript,
            'displayed_transcript' => $aiResponse ? $this->extractDisplayedTranscript($aiResponse, $transcript) : $transcript,
            'debug_transcript' => $aiResponse ? $this->normalizer->normalize($aiResponse, $transcript)['debug_transcript'] : $transcript,
            'source' => $source,
            'confidence' => $confidence,
            'stt_result' => $sttResult,
            'ai_response' => $aiResponse,
        ];
    }

    private function storeAudioAiFields(?AudioFile $audioFile, array $aiResponse): void
    {
        if (! $audioFile) {
            return;
        }

        $audioFile->update([
            'ai_provider' => $aiResponse['model_family'] ?? $aiResponse['provider'] ?? null,
            'ai_model' => $aiResponse['model_used'] ?? $aiResponse['model_size'] ?? $aiResponse['model_path'] ?? null,
            'ai_request_id' => $aiResponse['request_id'] ?? null,
            'ai_transcript' => $aiResponse['raw_transcript'] ?? $aiResponse['transcript'] ?? null,
            'ai_normalized_transcript' => $aiResponse['corrected_transcript'] ?? $aiResponse['normalized_transcript'] ?? null,
            'ai_confidence' => $this->nullableFloat($aiResponse['confidence'] ?? null),
            'ai_error' => $aiResponse['error'] ?? null,
            'ai_warnings' => $aiResponse['warnings'] ?? null,
            'ai_completed_at' => now(),
        ]);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function inferPromptType(array $context): string
    {
        $taskType = (string) ($context['task_type'] ?? '');
        $activityType = (string) ($context['activity_type'] ?? '');

        if (str_contains($taskType, 'letter') || str_contains($activityType, 'letter')) {
            return 'letter';
        }

        if (str_contains($taskType, 'sentence') || str_contains($activityType, 'sentence')) {
            return 'sentence';
        }

        $expected = trim((string) ($context['expected_text'] ?? ''));

        if ($expected === '') {
            return 'unknown';
        }

        if (mb_strlen($expected) === 1) {
            return 'letter';
        }

        return str_contains($expected, ' ') ? 'sentence' : 'word';
    }
}
