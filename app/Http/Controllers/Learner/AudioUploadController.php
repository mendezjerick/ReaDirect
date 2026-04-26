<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\Learner;
use App\Models\ModuleAttempt;
use App\Services\AudioStorageService;
use App\Services\STT\AudioTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AudioUploadController extends Controller
{
    public function store(Request $request, AudioStorageService $audioStorage, AudioTranscriptionService $transcription): JsonResponse
    {
        $validated = $request->validate([
            'audio' => AudioStorageService::validationRules(true),
            'context_type' => ['required', 'string', Rule::in(['assessment_task', 'module_activity', 'passage_reading', 'comprehension_optional'])],
            'assessment_attempt_id' => ['nullable', 'integer', 'exists:assessment_attempts,id'],
            'module_attempt_id' => ['nullable', 'integer', 'exists:module_attempts,id'],
            'item_id' => ['nullable', 'integer'],
            'task_type' => ['nullable', 'string', 'max:100'],
            'activity_type' => ['nullable', 'string', 'max:100'],
            'duration_seconds' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ]);

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
        $sttResult = $transcription->transcribeAudioFile($audioFile, $this->sttOptions($assessmentAttempt, $validated));

        return response()->json([
            'audio_file_id' => $audioFile->id,
            'audio_file_public_id' => $audioFile->public_id,
            'mime_type' => $audioFile->mime_type,
            'file_size' => $audioFile->file_size,
            'duration_seconds' => $audioFile->duration_seconds,
            'transcript' => $sttResult->transcript,
            'stt_confidence' => $sttResult->confidence,
            'transcript_source' => $sttResult->hasTranscript() ? 'stt_auto' : null,
            'stt_error' => $sttResult->error,
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
            ],
            'crla_task_2b_sentence' => [
                'prompt' => (string) ($item->prompt_snapshot['prompt'] ?? ''),
            ],
            default => [],
        };
    }
}
