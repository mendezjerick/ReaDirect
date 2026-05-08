<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\AI\AIAnalysisResolver;
use App\Services\AssessmentModeService;
use App\Services\AudioStorageService;
use App\Services\STT\AudioTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AudioUploadController extends Controller
{
    public function store(
        Request $request,
        AudioStorageService $audioStorage,
        AIAnalysisResolver $analysis,
        AudioTranscriptionService $transcription,
        AssessmentModeService $mode
    ): JsonResponse {
        $validated = $request->validate([
            'audio' => AudioStorageService::validationRules(true),
            'context_type' => ['required', 'string', Rule::in(['assessment_task', 'module_activity', 'passage_reading', 'comprehension_optional'])],
            'assessment_attempt_id' => ['nullable', 'integer', 'exists:assessment_attempts,id'],
            'module_attempt_id' => ['nullable', 'integer', 'exists:module_attempts,id'],
            'item_id' => ['nullable', 'integer'],
            'task_type' => ['nullable', 'string', 'max:100'],
            'activity_type' => ['nullable', 'string', 'max:100'],
            'duration_seconds' => AudioStorageService::durationValidationRules(),
        ], AudioStorageService::durationValidationMessages());

        $learner = Learner::find($request->session()->get('learner_id')) ?? Learner::firstOrFail();
        $assessmentAttempt = isset($validated['assessment_attempt_id'])
            ? AssessmentAttempt::where('learner_id', $learner->id)->findOrFail($validated['assessment_attempt_id'])
            : null;
        $moduleAttempt = isset($validated['module_attempt_id'])
            ? ModuleAttempt::where('learner_id', $learner->id)->findOrFail($validated['module_attempt_id'])
            : null;

        $audioFile = $audioStorage->store(
            file: $validated['audio'],
            learner: $learner,
            recordingContext: $validated['context_type'],
            assessmentAttempt: $assessmentAttempt,
            moduleAttempt: $moduleAttempt,
            durationSeconds: isset($validated['duration_seconds']) ? (float) $validated['duration_seconds'] : null,
            metadata: [
                'item_id' => $validated['item_id'] ?? null,
                'task_type' => $validated['task_type'] ?? null,
                'activity_type' => $validated['activity_type'] ?? null,
            ]
        );
        $canSeeRawAiPayload = $mode->canSeeRawAiPayload($request, $assessmentAttempt ?? $moduleAttempt, $learner);
        $context = $this->analysisContext($assessmentAttempt, $moduleAttempt, $validated, $canSeeRawAiPayload);
        $sttOptions = $this->sttOptions($assessmentAttempt, $validated);
        $resolved = $this->shouldUseFastLetterPath($validated)
            ? $this->fastLetterResolution($audioFile, $transcription, $sttOptions)
            : $analysis->resolve(null, $audioFile, $context, $sttOptions);
        $transcript = trim((string) ($resolved['transcript'] ?? ''));
        $transcriptionMessage = $this->transcriptionMessage($resolved, $canSeeRawAiPayload);

        $payload = [
            'audio_file_id' => $audioFile->id,
            'audio_file_public_id' => $audioFile->public_id,
            'mime_type' => $audioFile->mime_type,
            'duration_seconds' => $audioFile->duration_seconds,
            'transcription_status' => $transcript !== '' ? 'transcribed' : 'failed',
            'transcription_message' => $transcriptionMessage,
            'message' => $transcriptionMessage,
            'transcript' => $resolved['transcript'],
            'displayed_transcript' => $resolved['displayed_transcript'] ?? $resolved['transcript'],
            'retry_required' => (bool) ($resolved['ai_response']['retry_required'] ?? false),
            'learner_retry_message' => $resolved['ai_response']['learner_retry_message'] ?? null,
            'transcript_source' => $transcript !== '' ? $resolved['source'] : null,
        ];

        if (! $canSeeRawAiPayload) {
            return response()->json($payload);
        }

        return response()->json(array_merge($payload, [
            'file_size' => $audioFile->file_size,
            'raw_transcript' => $resolved['ai_response']['raw_transcript'] ?? $resolved['transcript'],
            'wav2vec2_transcript' => $resolved['ai_response']['wav2vec2_transcript'] ?? null,
            'corrected_transcript' => $resolved['ai_response']['corrected_transcript'] ?? $resolved['transcript'],
            'expected_text' => $resolved['ai_response']['expected_text'] ?? $context['expected_text'] ?? null,
            'prompt_type' => $resolved['ai_response']['prompt_type'] ?? $context['prompt_type'] ?? null,
            'asr_route' => $resolved['ai_response']['asr_route'] ?? null,
            'model_family' => $resolved['ai_response']['model_family'] ?? null,
            'model_used' => $resolved['ai_response']['model_used'] ?? null,
            'raw_wer' => $resolved['ai_response']['raw_wer'] ?? null,
            'corrected_wer' => $resolved['ai_response']['corrected_wer'] ?? null,
            'raw_cer' => $resolved['ai_response']['raw_cer'] ?? null,
            'corrected_cer' => $resolved['ai_response']['corrected_cer'] ?? null,
            'phonetic_similarity_score' => $resolved['ai_response']['phonetic_similarity_score'] ?? null,
            'composite_score' => $resolved['ai_response']['composite_score'] ?? null,
            'accepted' => $resolved['ai_response']['accepted'] ?? null,
            'normalization_applied' => $resolved['ai_response']['normalization_applied'] ?? false,
            'normalization_reason' => $resolved['ai_response']['normalization_reason'] ?? null,
            'correction_strategy_used' => $resolved['ai_response']['correction_strategy_used'] ?? null,
            'accepted_by_exact_match' => $resolved['ai_response']['accepted_by_exact_match'] ?? false,
            'accepted_by_letter_alias' => $resolved['ai_response']['accepted_by_letter_alias'] ?? $resolved['ai_response']['accepted_by_letter_normalization'] ?? false,
            'accepted_by_letter_lattice' => $resolved['ai_response']['accepted_by_letter_lattice'] ?? false,
            'accepted_by_vowel_tail' => $resolved['ai_response']['accepted_by_vowel_tail'] ?? false,
            'accepted_by_known_confusion' => $resolved['ai_response']['accepted_by_known_confusion'] ?? false,
            'accepted_by_phonetic_threshold' => $resolved['ai_response']['accepted_by_phonetic_threshold'] ?? false,
            'accepted_by_phoneme_evidence' => $resolved['ai_response']['accepted_by_phoneme_evidence'] ?? false,
            'critical_phoneme' => $resolved['ai_response']['critical_phoneme'] ?? null,
            'critical_phoneme_detected' => $resolved['ai_response']['critical_phoneme_detected'] ?? null,
            'threshold_used' => $resolved['ai_response']['threshold_used'] ?? null,
            'audio_quality' => $resolved['ai_response']['audio_quality'] ?? null,
            'pause_metrics' => $resolved['ai_response']['pause_metrics'] ?? null,
            'retry_required' => (bool) ($resolved['ai_response']['retry_required'] ?? false),
            'uncertain' => (bool) ($resolved['ai_response']['uncertain'] ?? false),
            'uncertainty_reasons' => $resolved['ai_response']['uncertainty_reasons'] ?? [],
            'quality_gate_failed' => (bool) ($resolved['ai_response']['quality_gate_failed'] ?? false),
            'stt_confidence' => $resolved['confidence'],
            'stt_error' => $resolved['stt_result']?->error,
            'ai_error' => $resolved['ai_response']['error'] ?? null,
            'ai_warnings' => $resolved['ai_response']['warnings'] ?? [],
        ]));
    }

    private function sttOptions(?AssessmentAttempt $assessmentAttempt, array $validated): array
    {
        if (($validated['context_type'] ?? null) !== 'assessment_task' || ! $assessmentAttempt || ! isset($validated['item_id'])) {
            return [];
        }

        $item = AssessmentAttemptItem::query()
            ->where('assessment_attempt_id', $assessmentAttempt->id)
            ->find($validated['item_id']);

        if (! $item) {
            return [];
        }

        return match ($validated['task_type'] ?? $item->task_type) {
            'crla_task_1_letter' => [
                'prompt' => (string) ($item->prompt_snapshot['prompt'] ?? ''),
                'model_path' => $this->letterModelPath(),
                'beam_size' => 1,
                'best_of' => 1,
                'temperature' => 0,
                'temperature_inc' => 0,
            ],
            'crla_task_2b_sentence' => [
                'prompt' => (string) ($item->prompt_snapshot['prompt'] ?? ''),
            ],
            default => [],
        };
    }

    private function analysisContext(?AssessmentAttempt $assessmentAttempt, ?ModuleAttempt $moduleAttempt, array $validated, bool $canShowDebug = false): array
    {
        $item = $this->assessmentItem($assessmentAttempt, $validated) ?? $this->moduleItem($moduleAttempt, $validated);
        $snapshot = $item?->prompt_snapshot ?? [];
        $payload = $snapshot['payload'] ?? [];
        $taskType = $validated['task_type'] ?? $item?->task_type ?? null;

        $expectedText = $taskType === 'crla_task_2b_sentence'
            ? ($payload['target_word'] ?? $payload['expected_answer'] ?? $snapshot['prompt'] ?? null)
            : ($payload['expected_answer'] ?? $payload['target_word'] ?? $snapshot['prompt'] ?? null);

        return [
            'expected_text' => $expectedText,
            'accepted_answers' => $snapshot['accepted_answers'] ?? [],
            'prompt_id' => $item?->source_csv_id,
            'module_key' => $moduleAttempt?->module?->key,
            'module_type' => $moduleAttempt?->module?->key,
            'activity_type' => $validated['activity_type'] ?? $item?->activity_type ?? $taskType,
            'assessment_type' => $assessmentAttempt?->attempt_type ?? ($moduleAttempt ? 'module_activity' : null),
            'item_id' => $item?->id ?? $validated['item_id'] ?? null,
            'learner_id' => $assessmentAttempt?->learner_id ?? $moduleAttempt?->learner_id,
            'attempt_id' => $assessmentAttempt?->id ?? $moduleAttempt?->id,
            'task_type' => $taskType,
            'current_scoring_context' => [
                'accepted_answers' => $snapshot['accepted_answers'] ?? [],
                'source_csv_id' => $item?->source_csv_id,
                'prompt_snapshot' => $snapshot,
            ],
            'content_metadata' => ['prompt_snapshot' => $snapshot],
            'debug' => $canShowDebug,
        ];
    }

    private function assessmentItem(?AssessmentAttempt $assessmentAttempt, array $validated): ?AssessmentAttemptItem
    {
        if (! $assessmentAttempt || ! isset($validated['item_id'])) {
            return null;
        }

        return AssessmentAttemptItem::query()
            ->where('assessment_attempt_id', $assessmentAttempt->id)
            ->find($validated['item_id']);
    }

    private function moduleItem(?ModuleAttempt $moduleAttempt, array $validated): ?ModuleAttemptItem
    {
        if (! $moduleAttempt || ! isset($validated['item_id'])) {
            return null;
        }

        return ModuleAttemptItem::query()
            ->where('module_attempt_id', $moduleAttempt->id)
            ->find($validated['item_id']);
    }

    private function shouldUseFastLetterPath(array $validated): bool
    {
        if ((bool) config('readirect_ai.enabled')) {
            return false;
        }

        return ($validated['context_type'] ?? null) === 'assessment_task'
            && ($validated['task_type'] ?? null) === 'crla_task_1_letter';
    }

    private function fastLetterResolution($audioFile, AudioTranscriptionService $transcription, array $sttOptions): array
    {
        $result = $transcription->transcribeAudioFile($audioFile, $sttOptions);

        return [
            'transcript' => trim((string) $result->transcript),
            'displayed_transcript' => trim((string) $result->transcript),
            'source' => $result->hasTranscript() ? 'stt_auto' : 'manual',
            'confidence' => $result->confidence,
            'stt_result' => $result,
            'ai_response' => null,
        ];
    }

    private function transcriptionMessage(array $resolved, bool $canShowDebug = false): ?string
    {
        if (trim((string) ($resolved['transcript'] ?? '')) !== '') {
            return null;
        }

        $aiError = $resolved['ai_response']['error'] ?? null;
        $aiWarnings = $resolved['ai_response']['warnings'] ?? [];
        $learnerRetryMessage = $resolved['ai_response']['learner_retry_message'] ?? null;
        $sttError = $resolved['stt_result']?->error ?? null;

        if (($resolved['ai_response']['retry_required'] ?? false) === true && is_string($learnerRetryMessage) && $learnerRetryMessage !== '') {
            return $learnerRetryMessage;
        }

        if (! $canShowDebug) {
            if ($aiError === 'unsupported_audio_type') {
                return 'That recording could not be used. Please try again.';
            }

            return 'We could not hear your answer clearly. Please try recording again.';
        }

        if ($aiError === 'readirect_ai_unavailable') {
            return 'Audio was saved, but Laravel could not connect to the ReaDirect AI service. Start the FastAPI service on the configured URL and try again.';
        }

        if ($aiError === 'unsupported_audio_type') {
            return 'Audio was saved, but this file type is not supported for transcription. Use WAV, WebM, MP3, M4A, OGG, or FLAC.';
        }

        if ($aiError === 'audio_file_not_found') {
            return 'Audio was saved, but the AI service could not read the stored file path.';
        }

        if (is_array($aiWarnings) && isset($aiWarnings[0]) && is_string($aiWarnings[0]) && $aiWarnings[0] !== '') {
            return 'Audio was saved, but AI transcription did not return text: '.$aiWarnings[0];
        }

        if (is_string($aiError) && $aiError !== '') {
            return 'Audio was saved, but AI transcription failed: '.$aiError.'.';
        }

        if (is_string($sttError) && $sttError !== '') {
            return 'Audio was saved, but fallback speech-to-text failed: '.$sttError.'.';
        }

        return 'Audio was saved, but no transcript was produced. Check that the AI service is running and the recording contains clear speech.';
    }

    private function letterModelPath(): ?string
    {
        $tinyModelPath = config('stt.whisper_cpp.letter_model_path');

        if (is_string($tinyModelPath) && $tinyModelPath !== '' && is_file($tinyModelPath)) {
            return $tinyModelPath;
        }

        return config('stt.whisper_cpp.model_path');
    }
}
