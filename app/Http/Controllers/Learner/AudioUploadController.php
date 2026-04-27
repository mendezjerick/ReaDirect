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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AudioUploadController extends Controller
{
    public function store(Request $request, AudioStorageService $audioStorage, AIAnalysisResolver $analysis): JsonResponse
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
        $resolved = $analysis->resolve(
            null,
            $audioFile,
            $this->analysisContext($assessmentAttempt, $moduleAttempt, $validated),
            $this->sttOptions($assessmentAttempt, $validated)
        );

        return response()->json([
            'audio_file_id' => $audioFile->id,
            'audio_file_public_id' => $audioFile->public_id,
            'mime_type' => $audioFile->mime_type,
            'file_size' => $audioFile->file_size,
            'duration_seconds' => $audioFile->duration_seconds,
            'transcript' => $resolved['transcript'],
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

        $expectedText = match ($taskType) {
            'crla_task_2b_sentence' => $snapshot['prompt'] ?? $payload['expected_answer'] ?? $payload['target_word'] ?? null,
            default => $payload['expected_answer'] ?? $payload['target_word'] ?? $snapshot['prompt'] ?? null,
        };

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
}
