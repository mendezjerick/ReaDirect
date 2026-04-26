<?php

namespace App\Services\AI;

use App\Models\Learner;

class LearnerHistoryPayloadBuilder
{
    public function recentForLearner(Learner $learner, int $limit = 10): array
    {
        $assessment = $learner->assessmentAttempts()
            ->with(['responses' => fn ($query) => $query->latest()->limit($limit)])
            ->latest()
            ->first()
            ?->responses ?? collect();

        $module = $learner->moduleAttempts()
            ->with(['responses' => fn ($query) => $query->latest()->limit($limit)])
            ->latest()
            ->first()
            ?->responses ?? collect();

        return $assessment
            ->concat($module)
            ->sortByDesc('created_at')
            ->take($limit)
            ->map(fn ($response) => [
                'prompt_id' => $response->metadata['source_csv_id'] ?? null,
                'expected_text' => $response->expected_answer,
                'actual_text' => $response->learner_transcript ?? $response->learner_answer ?? $response->response_text,
                'is_correct' => $response->is_correct,
                'similarity_label' => $response->ai_similarity_label,
                'error_type' => $response->ai_error_type ?? $response->error_type,
                'skill_signal' => $response->ai_skill_signal,
                'target_phoneme' => $response->ai_target_phoneme,
                'difficulty_level' => $response->metadata_json['prompt_snapshot']['difficulty'] ?? null,
                'timestamp' => $response->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
