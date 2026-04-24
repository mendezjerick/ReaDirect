<?php

namespace App\Services;

class ReadingComprehensionScoringService
{
    public const LOW_EMERGING = 'Low Emerging Reader';
    public const HIGH_EMERGING = 'High Emerging Reader';
    public const DEVELOPING = 'Developing Reader';
    public const TRANSITIONING = 'Transitioning Reader';
    public const GRADE_LEVEL = 'Reading at Grade Level';

    public function calculateAccuracyPercentage(int $incorrectWords): float
    {
        if ($incorrectWords < 0) {
            throw new \InvalidArgumentException('Incorrect word count cannot be negative.');
        }

        return max(0.0, 100.0 - ($incorrectWords * 2));
    }

    public function calculateComprehensionPercentage(int $correctAnswers, int $totalQuestions = 5): float
    {
        if ($correctAnswers < 0 || $totalQuestions <= 0 || $correctAnswers > $totalQuestions) {
            throw new \InvalidArgumentException('Comprehension inputs are outside the allowed range.');
        }

        return round(($correctAnswers / $totalQuestions) * 100, 2);
    }

    public function calculateFinalReadingScore(float $comprehensionPercentage, float $accuracyPercentage): float
    {
        $this->assertPercentage($comprehensionPercentage);
        $this->assertPercentage($accuracyPercentage);

        return round(($comprehensionPercentage * 0.60) + ($accuracyPercentage * 0.40), 2);
    }

    public function classifyReadingLevelFromFinalScore(float|int $finalReadingScore): string
    {
        $this->assertPercentage((float) $finalReadingScore);

        return match (true) {
            $finalReadingScore <= 25 => self::LOW_EMERGING,
            $finalReadingScore <= 50 => self::HIGH_EMERGING,
            $finalReadingScore <= 75 => self::DEVELOPING,
            $finalReadingScore <= 90 => self::TRANSITIONING,
            default => self::GRADE_LEVEL,
        };
    }

    private function assertPercentage(float $percentage): void
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Percentage must be between 0 and 100.');
        }
    }
}
