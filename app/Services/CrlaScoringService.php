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

    private function assertScoreRange(int $score): void
    {
        if ($score < 0 || $score > 10) {
            throw new \InvalidArgumentException('CRLA task score must be between 0 and 10.');
        }
    }
}
