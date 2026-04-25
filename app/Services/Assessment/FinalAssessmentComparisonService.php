<?php

namespace App\Services\Assessment;

use App\Models\AssessmentAttempt;

class FinalAssessmentComparisonService
{
    public function computeInitialVsFinal(array $initialResults, array $finalResults): array
    {
        $metrics = [
            'task_1_score',
            'task_2a_score',
            'task_2b_score',
            'crla_total_score',
            'reading_accuracy',
            'comprehension_percentage',
            'final_reading_score',
        ];

        $deltas = [];
        $percentChange = [];

        foreach ($metrics as $metric) {
            $initial = $this->numeric($initialResults[$metric] ?? null);
            $final = $this->numeric($finalResults[$metric] ?? null);
            $delta = $initial === null || $final === null ? null : round($final - $initial, 2);

            $deltas[$metric] = $delta;
            $percentChange[$metric] = $initial === null || $final === null || abs($initial) < 0.001
                ? null
                : round((($final - $initial) / $initial) * 100, 2);
        }

        return [
            'initial_scores' => $this->onlyKnown($initialResults, array_merge($metrics, [
                'crla_classification',
                'reading_classification',
            ])),
            'final_scores' => $this->onlyKnown($finalResults, array_merge($metrics, [
                'crla_classification',
                'reading_classification',
            ])),
            'deltas' => $deltas,
            'percent_change' => $percentChange,
            'summary' => $this->summaryMessage($deltas),
        ];
    }

    public function compareAttempts(?AssessmentAttempt $initial, AssessmentAttempt $final): array
    {
        return $this->computeInitialVsFinal(
            $initial?->toArray() ?? [],
            $final->toArray()
        );
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function onlyKnown(array $values, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $values[$key] ?? null])
            ->all();
    }

    private function summaryMessage(array $deltas): string
    {
        $crlaDelta = $deltas['crla_total_score'] ?? null;
        $readingDelta = $deltas['final_reading_score'] ?? null;

        if (($crlaDelta ?? 0) > 0 || ($readingDelta ?? 0) > 0) {
            return 'The learner improved in one or more final reassessment areas.';
        }

        if ($crlaDelta === null && $readingDelta === null) {
            return 'Comparison is pending until both assessment records are complete.';
        }

        return 'The learner completed the final reassessment. Review item responses for next support steps.';
    }
}
