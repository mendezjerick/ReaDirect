<?php

namespace App\Services;

use App\Models\ModuleAttempt;
use App\Models\ModuleAttemptItem;
use App\Support\ModuleSentenceText;
use Illuminate\Support\Collection;

class ModuleScoringService
{
    public function __construct(
        private readonly AnswerMatchingService $answerMatching,
        private readonly SentenceReadingScoringService $sentenceScoring,
    )
    {
    }

    public function scoreAnswer(ModuleAttemptItem $moduleAttemptItem, string $answer, ?float $durationSeconds = null, ?array $aiSignals = null): array
    {
        if (trim($answer) === '') {
            throw new \InvalidArgumentException('Missing answer cannot be scored.');
        }

        $snapshot = $moduleAttemptItem->prompt_snapshot ?? [];
        $acceptedAnswers = $snapshot['accepted_answers'] ?? [];
        $payload = $snapshot['payload'] ?? [];
        $points = (float) ($snapshot['points'] ?? $payload['points'] ?? 1);
        $expectedAnswer = $payload['expected_answer'] ?? $payload['target_sentence'] ?? $payload['target_word'] ?? null;
        $expectedAnswer = is_string($expectedAnswer)
            ? ModuleSentenceText::scoringTarget(
                $expectedAnswer,
                (string) ($payload['module_key'] ?? ''),
                (string) ($payload['activity_type'] ?? $moduleAttemptItem->activity_type ?? ''),
            )
            : $expectedAnswer;

        if ($this->isModuleThreeSentenceItem($moduleAttemptItem, $payload, $expectedAnswer)) {
            return $this->scoreSentenceItem(
                expectedAnswer: (string) $expectedAnswer,
                answer: $answer,
                points: $points,
                payload: $payload,
                durationSeconds: $durationSeconds,
                aiSignals: $aiSignals ?? [],
            );
        }

        $isCorrect = $this->answerMatching->isAcceptedAnswer($answer, $acceptedAnswers);

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $points : 0,
            'possible_score' => $points,
            'expected_answer' => $expectedAnswer,
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

    private function scoreSentenceItem(
        string $expectedAnswer,
        string $answer,
        float $points,
        array $payload,
        ?float $durationSeconds,
        array $aiSignals,
    ): array {
        $timingTargets = collect($payload)
            ->only([
                'target_read_time_seconds',
                'min_fluent_time_seconds',
                'max_fluent_time_seconds',
                'target_wcpm',
                'min_expected_wcpm',
                'max_expected_wcpm',
                'pace_feedback_rule',
                'pace_mastery_required',
            ])
            ->all();
        $evaluation = $this->sentenceScoring->evaluate($expectedAnswer, $answer, $durationSeconds, $aiSignals, $timingTargets);
        $wordCorrect = (int) ($evaluation['errors'] ?? 0) === 0
            && (int) ($evaluation['correct_words'] ?? 0) >= (int) ($evaluation['total_words_read'] ?? 0);
        $paceLabel = (string) ($evaluation['pace_label'] ?? 'unknown');
        $paceMasteryRequired = (bool) ($payload['pace_mastery_required'] ?? false);
        $paceCorrect = ! $paceMasteryRequired || $paceLabel === 'fluent';
        $isCorrect = $wordCorrect && $paceCorrect;

        return [
            'is_correct' => $isCorrect,
            'score' => $isCorrect ? $points : 0,
            'possible_score' => $points,
            'expected_answer' => $expectedAnswer,
            'error_type' => $isCorrect ? null : $this->sentenceErrorType($wordCorrect, $paceLabel, $evaluation),
            'sentence_evaluation' => $evaluation,
            'wcpm' => $evaluation['wcpm'] ?? null,
            'pace_label' => $paceLabel,
            'pace_mastery_required' => $paceMasteryRequired,
        ];
    }

    private function isModuleThreeSentenceItem(ModuleAttemptItem $item, array $payload, mixed $expectedAnswer): bool
    {
        $moduleKey = (string) ($payload['module_key'] ?? '');
        $activityType = (string) ($payload['activity_type'] ?? $item->activity_type ?? '');

        return $moduleKey === 'module_3'
            || str_contains($activityType, 'sentence')
            || str_contains($activityType, 'fluency')
            || (is_string($expectedAnswer) && str_contains(trim($expectedAnswer), ' '));
    }

    private function sentenceErrorType(bool $wordCorrect, string $paceLabel, array $evaluation): string
    {
        if ($wordCorrect) {
            return match ($paceLabel) {
                'too_fast' => 'pace_too_fast',
                'too_slow' => 'pace_too_slow',
                default => 'pace_unknown',
            };
        }

        $deletions = (int) ($evaluation['deletions'] ?? 0);
        $substitutions = (int) ($evaluation['substitutions'] ?? 0);
        $insertions = (int) ($evaluation['insertions'] ?? 0);

        if ($deletions >= $substitutions && $deletions >= $insertions) {
            return 'skipped_word';
        }

        if ($insertions >= $substitutions) {
            return 'insertion';
        }

        return 'substitution';
    }
}
