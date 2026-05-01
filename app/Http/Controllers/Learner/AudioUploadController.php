<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Services\AI\AIAnalysisResolver;
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
        AudioTranscriptionService $transcription
    ): JsonResponse
    {
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
        $context = $this->analysisContext($assessmentAttempt, $moduleAttempt, $validated);
        $sttOptions = $this->sttOptions($assessmentAttempt, $validated);
        $resolved = $this->shouldUseFastLetterPath($validated)
            ? $this->fastLetterResolution($audioFile, $transcription, $sttOptions)
            : $analysis->resolve(null, $audioFile, $context, $sttOptions);

        return response()->json([
            'audio_file_id' => $audioFile->id,
            'audio_file_public_id' => $audioFile->public_id,
            'mime_type' => $audioFile->mime_type,
            'file_size' => $audioFile->file_size,
            'duration_seconds' => $audioFile->duration_seconds,
            'transcript' => $resolved['transcript'],
            'displayed_transcript' => $resolved['displayed_transcript'] ?? $resolved['transcript'],
            'raw_transcript' => $resolved['ai_response']['raw_transcript'] ?? $resolved['transcript'],
            'corrected_transcript' => $resolved['ai_response']['corrected_transcript'] ?? $resolved['transcript'],
            'raw_wer' => $resolved['ai_response']['raw_wer'] ?? null,
            'corrected_wer' => $resolved['ai_response']['corrected_wer'] ?? null,
            'phonetic_similarity_score' => $resolved['ai_response']['phonetic_similarity_score'] ?? null,
            'normalization_applied' => $resolved['ai_response']['normalization_applied'] ?? false,
            'normalization_reason' => $resolved['ai_response']['normalization_reason'] ?? null,
            'correction_strategy_used' => $resolved['ai_response']['correction_strategy_used'] ?? null,
            'accepted_by_exact_match' => $resolved['ai_response']['accepted_by_exact_match'] ?? false,
            'accepted_by_letter_normalization' => $resolved['ai_response']['accepted_by_letter_normalization'] ?? false,
            'accepted_by_letter_lattice' => $resolved['ai_response']['accepted_by_letter_lattice'] ?? false,
            'accepted_by_known_confusion' => $resolved['ai_response']['accepted_by_known_confusion'] ?? false,
            'accepted_by_phonetic_threshold' => $resolved['ai_response']['accepted_by_phonetic_threshold'] ?? false,
            'threshold_used' => $resolved['ai_response']['threshold_used'] ?? null,
            'stt_confidence' => $resolved['confidence'],
            'transcript_source' => trim((string) $resolved['transcript']) !== '' ? $resolved['source'] : null,
            'stt_error' => $resolved['stt_result']?->error,
            'ai_error' => $resolved['ai_response']['error'] ?? null,
        ]);
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

    private function analysisContext(?AssessmentAttempt $assessmentAttempt, ?ModuleAttempt $moduleAttempt, array $validated): array
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
            'activity_type' => $validated['activity_type'] ?? $item?->activity_type ?? null,
            'task_type' => $taskType,
            'content_metadata' => ['prompt_snapshot' => $snapshot],
            'debug' => (bool) config('readirect_ai.debug.show_admin_debug'),
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

    private function letterModelPath(): ?string
    {
        $tinyModelPath = config('stt.whisper_cpp.letter_model_path');

        if (is_string($tinyModelPath) && $tinyModelPath !== '' && is_file($tinyModelPath)) {
            return $tinyModelPath;
        }

        return config('stt.whisper_cpp.model_path');
    }
}
