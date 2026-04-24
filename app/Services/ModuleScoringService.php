<?php

namespace App\Services;

use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use Illuminate\Support\Collection;

class ModuleScoringService
{
    public function __construct(private readonly AnswerMatchingService $answerMatching)
    {
    }

    public function scoreAnswer(ModuleAttemptItem $moduleAttemptItem, string $answer): array
    {
        if (trim($answer) === '') {
            throw new \InvalidArgumentException('Missing answer cannot be scored.');
        }

        $snapshot = $moduleAttemptItem->prompt_snapshot ?? [];
        $acceptedAnswers = $snapshot['accepted_answers'] ?? [];
        $payload = $snapshot['payload'] ?? [];
        $points = (float) ($snapshot['points'] ?? $payload['points'] ?? 1);
        $isCorrect = $this->answerMatching->isAcceptedAnswer($answer, $acceptedAnswers);

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $points : 0,
            'possible_score' => $points,
            'expected_answer' => $payload['expected_answer'] ?? $payload['target_word'] ?? null,
            'error_type' => $isCorrect ? null : 'incorrect_general',
        ];
    }

    public function calculatePerformanceScore(ModuleAttempt $moduleAttempt): float
    {
        return $this->calculateScore(
            $moduleAttempt->responses()->with('moduleAttemptItem')->get()
                ->filter(fn ($response) => ! (bool) $response->is_mastery_item)
        );
    }

    public function calculateMasteryScore(ModuleAttempt $moduleAttempt): float
    {
        return $this->calculateScore(
            $moduleAttempt->responses()->with('moduleAttemptItem')->get()
                ->filter(fn ($response) => (bool) $response->is_mastery_item)
        );
    }

    private function calculateScore(Collection $responses): float
    {
        if ($responses->isEmpty()) {
            return 0.0;
        }

        $earned = $responses->sum('score');
        $possible = $responses->sum(function ($response): float {
            $snapshot = $response->moduleAttemptItem?->prompt_snapshot ?? [];

            return (float) ($snapshot['points'] ?? ($snapshot['payload']['points'] ?? 1));
        });

        if ($possible <= 0) {
            return 0.0;
        }

        return round(($earned / $possible) * 100, 2);
    }
}
