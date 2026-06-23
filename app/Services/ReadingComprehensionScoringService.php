<?php

namespace App\Services;

class ReadingComprehensionScoringService
{
    public const ASSESSMENT_COMPREHENSION_QUESTION_COUNT = 5;

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

    public function calculateIncorrectWordCount(string $expectedPassage, string $transcript, array $wordAlignment = []): int
    {
        $alignmentIncorrectCount = $this->incorrectWordCountFromAlignment($expectedPassage, $wordAlignment);

        if ($alignmentIncorrectCount !== null) {
            return $alignmentIncorrectCount;
        }

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

    public function analyzePassageReading(string $expectedPassage, string $transcript, array $wordAlignment = []): array
    {
        $expectedWords = $this->normalizedWords($expectedPassage);
        $actualWords = $this->normalizedWords($transcript);
        $alignmentIncorrectCount = $this->incorrectWordCountFromAlignment($expectedPassage, $wordAlignment);

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
            'incorrect_count' => $alignmentIncorrectCount ?? $this->calculateIncorrectWordCount($expectedPassage, $transcript),
            'semantic_matches' => $semanticMatches,
            'exact_matches' => $exactMatches,
        ];
    }

    public function calculateComprehensionPercentage(int $correctAnswers, int $totalQuestions = self::ASSESSMENT_COMPREHENSION_QUESTION_COUNT): float
    {
        if ($correctAnswers < 0 || $totalQuestions <= 0 || $correctAnswers > $totalQuestions) {
            throw new \InvalidArgumentException('Comprehension inputs are outside the allowed range.');
        }

        return round(($correctAnswers / $totalQuestions) * 100, 2);
    }

    public function normalizeMultipleChoiceSelection(?string $answer, array $choices = []): string
    {
        $answer = trim((string) $answer);

        if (preg_match('/^[A-D]$/i', $answer) === 1) {
            return strtoupper($answer);
        }

        return '';
    }

    public function isCorrectMultipleChoiceAnswer(?string $answer, ?string $correctChoice, array $choices = []): bool
    {
        $selectedChoice = $this->normalizeMultipleChoiceSelection($answer, $choices);

        return $selectedChoice !== ''
            && $selectedChoice === strtoupper(trim((string) $correctChoice));
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

    public function classifyReadingLevelWithRule(float|int $finalReadingScore): array
    {
        $classification = $this->classifyReadingLevelFromFinalScore($finalReadingScore);

        return [
            'classification' => $classification,
            'rule_applied' => 'READING_LEVEL_CLASSIFICATION_V1',
            'matched_threshold' => match ($classification) {
                self::LOW_EMERGING => '0-25',
                self::HIGH_EMERGING => '25.01-50',
                self::DEVELOPING => '50.01-75',
                self::TRANSITIONING => '75.01-90',
                self::GRADE_LEVEL => '90.01-100',
            },
            'condition' => match ($classification) {
                self::LOW_EMERGING => 'Final reading score is 0-25.',
                self::HIGH_EMERGING => 'Final reading score is above 25 and up to 50.',
                self::DEVELOPING => 'Final reading score is above 50 and up to 75.',
                self::TRANSITIONING => 'Final reading score is above 75 and up to 90.',
                self::GRADE_LEVEL => 'Final reading score is above 90.',
            },
        ];
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

    private function incorrectWordCountFromAlignment(string $expectedPassage, array $wordAlignment): ?int
    {
        if ($wordAlignment === []) {
            return null;
        }

        $expectedEntries = array_values(array_filter(
            $wordAlignment,
            static fn ($item): bool => is_array($item)
                && array_key_exists('expected_word', $item)
                && $item['expected_word'] !== null
        ));

        $expectedWords = $this->normalizedWords($expectedPassage);

        if ($expectedEntries === [] || count($expectedEntries) !== count($expectedWords)) {
            return null;
        }

        $acceptedStatuses = [
            'correct',
            'exact_correct',
            'accepted_by_dynamic_expected_word_correction',
            'accepted_by_homophone',
            'accepted_by_phoneme_similarity',
            'accepted_by_gop',
            'accepted_by_asr_spelling_variant',
            'accepted_by_split_merge',
        ];

        return count(array_filter(
            $expectedEntries,
            static fn ($item): bool => ! ((bool) ($item['counts_as_correct'] ?? false)
                || in_array((string) ($item['status'] ?? ''), $acceptedStatuses, true))
        ));
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
