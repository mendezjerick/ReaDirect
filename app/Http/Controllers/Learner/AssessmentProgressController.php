<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\LearningContent;
use App\Support\CurrentLearner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentProgressController extends Controller
{
    public function storeComprehensionChoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assessment_attempt_id' => ['required', 'integer', 'exists:assessment_attempts,id'],
            'question_id' => ['required', 'string'],
            'answer' => ['required', 'string', 'max:255'],
        ]);

        $learner = CurrentLearner::require($request);
        $attempt = AssessmentAttempt::query()
            ->where('id', $validated['assessment_attempt_id'])
            ->where('learner_id', $learner->id)
            ->whereIn('attempt_type', ['diagnostic', 'final_reassessment'])
            ->firstOrFail();

        if ($attempt->completed_at !== null) {
            return response()->json(['saved' => false], 409);
        }

        $question = LearningContent::query()
            ->where('content_type', 'comprehension_question')
            ->where('payload->source_csv_id', $validated['question_id'])
            ->first();

        $existing = AssessmentTaskResponse::query()
            ->where('assessment_attempt_id', $attempt->id)
            ->where('task_type', 'comprehension_question')
            ->where('metadata->source_csv_id', $validated['question_id'])
            ->first();

        $response = $existing ?? new AssessmentTaskResponse([
            'assessment_attempt_id' => $attempt->id,
            'task_type' => 'comprehension_question',
            'metadata' => ['source_csv_id' => $validated['question_id']],
        ]);

        $response->fill([
            'learner_id' => $learner->id,
            'learning_content_id' => $question?->id,
            'task_key' => $attempt->attempt_type === 'final_reassessment' ? 'final_reading_comprehension_progress' : 'reading_comprehension_progress',
            'item_number' => (int) ($question?->payload['sequence'] ?? 0),
            'prompt' => $question?->prompt,
            'expected_answer' => $question?->payload['correct_answer'] ?? null,
            'selected_answer' => $validated['answer'],
            'response_text' => $validated['answer'],
            'rule_applied' => 'COMPREHENSION_PROGRESS_SAVE_V1',
            'metadata_json' => ['choices' => $question?->payload['choices'] ?? []],
        ])->save();

        return response()->json(['saved' => true]);
    }
}
