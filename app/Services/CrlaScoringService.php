<?php

namespace App\Services;

class CrlaScoringService
{
    public const FULL_REFRESHER = 'Full Refresher';
    public const MODERATE_REFRESHER = 'Moderate Refresher';
    public const LIGHT_REFRESHER = 'Light Refresher';
    public const GRADE_READY = 'Grade Ready';

    public function shouldRequireTask2A(int $taskOneScore): bool
    {
        $this->assertScoreRange($taskOneScore);

        return $taskOneScore <= 6;
    }

    public function routeTaskOne(int $taskOneScore): array
    {
        $requiresTask2A = $this->shouldRequireTask2A($taskOneScore);

        return [
            'requires_task_2a' => $requiresTask2A,
            'assigned_task_2a_score' => $requiresTask2A ? null : 10,
            'next_task' => $requiresTask2A ? 'task_2a' : 'task_2b',
            'rule_applied' => 'CRLA_TASK_1_ROUTING_V1',
        ];
    }

    public function calculateTotalScore(int $taskOneScore, int $taskTwoAScore, int $taskTwoBScore): int
    {
        $this->assertScoreRange($taskOneScore);
        $this->assertScoreRange($taskTwoAScore);
        $this->assertScoreRange($taskTwoBScore);

        return $taskOneScore + $taskTwoAScore + $taskTwoBScore;
    }

    public function classifyTotalScore(int $totalScore): string
    {
        if ($totalScore < 0 || $totalScore > 30) {
            throw new \InvalidArgumentException('CRLA total score must be between 0 and 30.');
        }

        return match (true) {
            $totalScore <= 10 => self::FULL_REFRESHER,
            $totalScore <= 16 => self::MODERATE_REFRESHER,
            $totalScore <= 26 => self::LIGHT_REFRESHER,
            default => self::GRADE_READY,
        };
    }

    public function classifyTotalScoreWithRule(int $totalScore): array
    {
        $classification = $this->classifyTotalScore($totalScore);

        return [
            'classification' => $classification,
            'rule_applied' => 'CRLA_TOTAL_CLASSIFICATION_V1',
            'matched_threshold' => match ($classification) {
                self::FULL_REFRESHER => '0-10',
                self::MODERATE_REFRESHER => '11-16',
                self::LIGHT_REFRESHER => '17-26',
                self::GRADE_READY => '27-30',
            },
            'condition' => match ($classification) {
                self::FULL_REFRESHER => 'CRLA total score is 0-10.',
                self::MODERATE_REFRESHER => 'CRLA total score is 11-16.',
                self::LIGHT_REFRESHER => 'CRLA total score is 17-26.',
                self::GRADE_READY => 'CRLA total score is 27-30.',
            },
        ];
    }

    private function assertScoreRange(int $score): void
    {
        if ($score < 0 || $score > 10) {
            throw new \InvalidArgumentException('CRLA task score must be between 0 and 10.');
        }
    }
}
