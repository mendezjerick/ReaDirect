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

    public function calculateIncorrectWordCount(string $expectedPassage, string $transcript): int
    {
        $expectedWords = $this->normalizedWords($expectedPassage);
        $actualWords = $this->normalizedWords($transcript);

        if ($expectedWords === []) {
            return 0;
        }

        if ($actualWords === []) {
            return min(count($expectedWords), 50);
        }

        $distance = $this->wordLevenshteinDistance($expectedWords, $actualWords);

        return min($distance, 50);
    }

    public function analyzePassageReading(string $expectedPassage, string $transcript): array
    {
        $expectedWords = $this->normalizedWords($expectedPassage);
        $actualWords = $this->normalizedWords($transcript);

        if ($expectedWords === []) {
            return [
                'incorrect_count' => 0,
                'semantic_matches' => 0,
                'exact_matches' => 0,
            ];
        }

        $exactMatches = 0;
        $semanticMatches = 0;
        $pairCount = min(count($expectedWords), count($actualWords));

        for ($index = 0; $index < $pairCount; $index++) {
            if ($expectedWords[$index] === $actualWords[$index]) {
                $exactMatches++;
                continue;
            }

            if ($this->isMeaningPreservingMatch($expectedWords[$index], $actualWords[$index])) {
                $semanticMatches++;
            }
        }

        return [
            'incorrect_count' => $this->calculateIncorrectWordCount($expectedPassage, $transcript),
            'semantic_matches' => $semanticMatches,
            'exact_matches' => $exactMatches,
        ];
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

    private function normalizedWords(string $text): array
    {
        $normalized = preg_replace('/[^a-z0-9\\s]+/i', ' ', strtolower($text)) ?? '';
        $normalized = preg_replace('/\\s+/', ' ', trim($normalized)) ?? '';

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    private function wordLevenshteinDistance(array $expectedWords, array $actualWords): int
    {
        $expectedCount = count($expectedWords);
        $actualCount = count($actualWords);
        $distances = [];

        for ($i = 0; $i <= $expectedCount; $i++) {
            $distances[$i] = array_fill(0, $actualCount + 1, 0);
            $distances[$i][0] = $i;
        }

        for ($j = 0; $j <= $actualCount; $j++) {
            $distances[0][$j] = $j;
        }

        for ($i = 1; $i <= $expectedCount; $i++) {
            for ($j = 1; $j <= $actualCount; $j++) {
                $cost = $this->wordsEquivalent($expectedWords[$i - 1], $actualWords[$j - 1]) ? 0 : 1;
                $distances[$i][$j] = min(
                    $distances[$i - 1][$j] + 1,
                    $distances[$i][$j - 1] + 1,
                    $distances[$i - 1][$j - 1] + $cost,
                );
            }
        }

        return $distances[$expectedCount][$actualCount];
    }

    private function wordsEquivalent(string $expectedWord, string $actualWord): bool
    {
        return $expectedWord === $actualWord || $this->isMeaningPreservingMatch($expectedWord, $actualWord);
    }

    private function isMeaningPreservingMatch(string $expectedWord, string $actualWord): bool
    {
        return $this->canonicalWord($expectedWord) === $this->canonicalWord($actualWord);
    }

    private function canonicalWord(string $word): string
    {
        $word = strtolower(trim($word));

        if ($word === '') {
            return '';
        }

        $canonicalGroups = [
            ['small', 'little'],
            ['big', 'large'],
            ['mom', 'mother', 'mama'],
            ['dad', 'father', 'papa'],
            ['job', 'work'],
            ['kid', 'child'],
            ['kids', 'children'],
            ['see', 'saw', 'seen'],
            ['run', 'runs', 'ran', 'running'],
            ['hop', 'hops', 'hopped', 'hopping'],
            ['wash', 'washes', 'washed', 'washing'],
            ['count', 'counts', 'counted', 'counting'],
            ['eat', 'eats', 'ate', 'eating'],
            ['find', 'finds', 'found', 'finding'],
            ['say', 'says', 'said', 'saying'],
            ['fill', 'fills', 'filled', 'filling'],
            ['feed', 'feeds', 'fed', 'feeding'],
            ['hen', 'hens'],
            ['egg', 'eggs'],
            ['hand', 'hands'],
            ['bee', 'be'],
            ['sea', 'see'],
            ['two', 'too', 'to'],
            ['one', 'won'],
        ];

        foreach ($canonicalGroups as $group) {
            if (in_array($word, $group, true)) {
                return $group[0];
            }
        }

        return $word;
    }
}
