<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentAttemptItem;
use App\Models\AssessmentTaskResponse;
use Illuminate\Support\Collection;

class TaskTwoARhymeDecisionScoringService
{
    public function score(AssessmentAttempt $attempt, Collection $items, array $responses, string $rule): int
    {
        $score = 0;

        foreach ($items as $item) {
            $submittedIndex = collect($responses)->search(fn ($response) => (int) ($response['assessment_attempt_item_id'] ?? 0) === (int) $item->id);
            $submitted = $submittedIndex === false ? [] : $responses[$submittedIndex];
            $selectedAnswer = $this->normalizeAnswer((string) ($submitted['answer'] ?? ''));
            $correctAnswer = $this->correctAnswer($item);
            $isCorrect = $selectedAnswer === $correctAnswer;
            $score += $isCorrect ? 1 : 0;

            AssessmentTaskResponse::updateOrCreate(
                ['assessment_attempt_id' => $attempt->id, 'assessment_attempt_item_id' => $item->id],
                [
                    'learner_id' => $attempt->learner_id,
                    'learning_content_id' => $item->learning_content_id,
                    'audio_file_id' => null,
                    'task_key' => $attempt->attempt_type === 'final_reassessment' ? 'final_'.$item->task_type : $item->task_type,
                    'task_type' => $item->task_type,
                    'item_number' => $item->sequence,
                    'prompt' => $this->audioScript($item),
                    'expected_answer' => $correctAnswer,
                    'learner_transcript' => null,
                    'transcript_source' => 'button',
                    'stt_confidence' => null,
                    'selected_answer' => $selectedAnswer,
                    'response_text' => $selectedAnswer,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'error_type' => $isCorrect ? null : 'incorrect_rhyme_decision',
                    'rule_applied' => $rule,
                    'metadata' => ['source_csv_id' => $item->source_csv_id],
                    'metadata_json' => [
                        'prompt_snapshot' => $item->prompt_snapshot,
                        'word_1' => $item->prompt_snapshot['payload']['word_1'] ?? null,
                        'word_2' => $item->prompt_snapshot['payload']['word_2'] ?? null,
                        'is_rhyme' => (bool) ($item->prompt_snapshot['payload']['is_rhyme'] ?? false),
                        'correct_answer' => $correctAnswer,
                        'selected_answer' => $selectedAnswer,
                        'asr_used' => false,
                    ],
                ]
            );

            $item->update(['answered_at' => now()]);
        }

        return $score;
    }

    public function normalizeAnswer(string $answer): string
    {
        return strtolower(trim($answer)) === 'yes' ? 'yes' : 'no';
    }

    private function correctAnswer(AssessmentAttemptItem $item): string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];
        $answer = strtolower(trim((string) ($payload['correct_answer'] ?? '')));

        return $answer === 'yes' ? 'yes' : 'no';
    }

    private function audioScript(AssessmentAttemptItem $item): string
    {
        $payload = $item->prompt_snapshot['payload'] ?? [];

        return (string) ($payload['audio_script'] ?? $payload['vivian_prompt_script'] ?? $item->prompt_snapshot['prompt'] ?? '');
    }
}
