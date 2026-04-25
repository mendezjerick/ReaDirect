<?php

namespace App\Services\Scoring;

class AnswerSimilarityService
{
    public function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[[:punct:]]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    public function levenshteinDistance(string $expected, string $actual): int
    {
        return levenshtein($this->normalize($expected), $this->normalize($actual));
    }

    public function similarityPercentage(string $expected, string $actual): float
    {
        $expected = $this->normalize($expected);
        $actual = $this->normalize($actual);

        if ($expected === '' || $actual === '') {
            return 0.0;
        }

        $maxLength = max(mb_strlen($expected), mb_strlen($actual));

        if ($maxLength === 0) {
            return 100.0;
        }

        return round(max(0, (1 - (levenshtein($expected, $actual) / $maxLength)) * 100), 2);
    }

    public function classifySimilarity(string $expected, string $actual): string
    {
        $expected = $this->normalize($expected);
        $actual = $this->normalize($actual);

        if ($actual === '') {
            return 'blank';
        }

        if ($expected === $actual) {
            return 'exact';
        }

        $distance = levenshtein($expected, $actual);
        $percentage = $this->similarityPercentage($expected, $actual);

        if ($distance === 1 || $percentage >= 80) {
            return 'very_close';
        }

        if ($percentage >= 60) {
            return 'close';
        }

        if ($percentage >= 35) {
            return 'somewhat_close';
        }

        return 'far';
    }

    public function detectErrorType(string $expected, string $actual, bool $isCorrect = false): string
    {
        $expected = $this->normalize($expected);
        $actual = $this->normalize($actual);

        if ($isCorrect) {
            return 'correct';
        }

        if ($actual === '') {
            return 'blank';
        }

        if (str_contains($expected, ' ') && ! str_contains($actual, ' ')) {
            return 'skipped_word';
        }

        if (mb_strlen($actual) < mb_strlen($expected)) {
            return 'omission';
        }

        if ($expected !== '' && $actual !== '' && mb_substr($expected, 0, 1) !== mb_substr($actual, 0, 1)) {
            return 'initial_sound_error';
        }

        if ($expected !== '' && $actual !== '' && mb_substr($expected, -1) !== mb_substr($actual, -1)) {
            return 'final_sound_error';
        }

        if ($this->middleVowel($expected) && $this->middleVowel($expected) !== $this->middleVowel($actual)) {
            return 'vowel_error';
        }

        return 'incorrect_general';
    }

    private function middleVowel(string $value): ?string
    {
        preg_match('/[aeiou]/', $value, $matches);

        return $matches[0] ?? null;
    }
}
