<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTaskResponse;
use App\Models\LearningContent;
use App\Services\ReadingComprehensionScoringService;
use App\Support\CurrentLearner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentProgressController extends Controller
{
    public function storeComprehensionChoice(Request $request, ReadingComprehensionScoringService $reading): JsonResponse
    {
        $validated = $request->validate([
            'assessment_attempt_id' => ['required', 'integer', 'exists:assessment_attempts,id'],
            'question_id' => ['required', 'string'],
            'answer' => ['required', 'string', 'regex:/^[A-D]$/i'],
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
        $choices = $question?->payload['choices'] ?? [];
        $selectedChoice = $reading->normalizeMultipleChoiceSelection($validated['answer'], $choices);
        $selectedAnswerText = $choices[$selectedChoice] ?? $validated['answer'];

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
            'expected_answer' => $question?->payload['correct_choice'] ?? null,
            'selected_answer' => $selectedChoice,
            'response_text' => $selectedAnswerText,
            'rule_applied' => 'COMPREHENSION_PROGRESS_SAVE_V1',
            'metadata_json' => [
                'choices' => $choices,
                'correct_choice' => $question?->payload['correct_choice'] ?? null,
                'correct_answer' => $question?->payload['correct_answer'] ?? null,
                'selected_choice' => $selectedChoice,
            ],
        ])->save();

        return response()->json(['saved' => true]);
    }
}
