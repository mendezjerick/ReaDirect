<?php

namespace App\Services;

class SentenceReadingScoringService
{
    public const MOSTLY_CORRECT = 'mostly correct';
    public const A_LITTLE_RUSHED = 'a little rushed';
    public const A_LITTLE_SLOW = 'a little slow';
    public const MISSING_ONE_WORD = 'missing one word';
    public const RIGHT_WORDS_BUT_UNCLEAR = 'saying the right words but unclear';
    public const TRANSCRIPT_MATCHED = 'transcript matched';
    public const MIXED_RESULTS = 'mixed results';

    public function evaluate(string $expectedSentence, string $actualTranscript, ?float $durationSeconds = null, ?array $aiSignals = null): array
    {
        $expectedWords = $this->normalizedWords($expectedSentence);
        $actualWords = $this->normalizedWords($actualTranscript);

        if ($expectedWords === []) {
            return [
                'accuracy_percentage' => 0,
                'score_ten' => 0.0,
                'matched_words' => 0,
                'total_words' => 0,
                'missing_words' => 0,
                'feedback_label' => self::RIGHT_WORDS_BUT_UNCLEAR,
                'is_rushed' => false,
                'is_slow' => false,
            ];
        }

        $totalWords = count($expectedWords);
        $distance = $this->wordLevenshteinDistance($expectedWords, $actualWords);
        $matchedWords = max(0, $totalWords - min($distance, $totalWords));
        $missingWords = max(0, $totalWords - count($actualWords));

        $normalizedExpected = implode(' ', $expectedWords);
        $normalizedActual = implode(' ', $actualWords);
        $compactExpected = $this->compactForm($expectedSentence);
        $compactActual = $this->compactForm($actualTranscript);

        $exactSentenceMatch = $normalizedExpected !== '' && $normalizedExpected === $normalizedActual;
        $compactSentenceMatch = $compactExpected !== '' && $compactExpected === $compactActual;

        $textAccuracy = (int) round(($matchedWords / $totalWords) * 100);

        if ($exactSentenceMatch) {
            $textAccuracy = 100;
            $matchedWords = $totalWords;
            $missingWords = 0;
        } elseif ($compactSentenceMatch) {
            // The learner likely said the right sentence but blended the word
            // boundaries together. Keep this from being graded like a full miss.
            $textAccuracy = max($textAccuracy, 80);
            $matchedWords = max($matchedWords, $totalWords - 1);
        }

        $characterSimilarity = $this->similarityPercentage($aiSignals['character_similarity'] ?? null);
        $phonemeSimilarity = $this->similarityPercentage($aiSignals['phoneme_similarity'] ?? null);
        $targetWordCharacterSimilarity = $this->similarityPercentage($aiSignals['target_word_character_similarity'] ?? null);
        $targetWordPhonemeSimilarity = $this->similarityPercentage($aiSignals['target_word_phoneme_similarity'] ?? null);
        $strongPronunciationSignal = max(array_filter([
            $characterSimilarity,
            $phonemeSimilarity,
            $targetWordCharacterSimilarity,
            $targetWordPhonemeSimilarity,
        ], static fn (?int $value) => $value !== null) ?: [0]);

        $hasPronunciationSignals = $phonemeSimilarity !== null || $characterSimilarity !== null;
        $hasTargetWordSignals = $targetWordPhonemeSimilarity !== null || $targetWordCharacterSimilarity !== null;
        $accuracy = $textAccuracy;

        if ($targetWordPhonemeSimilarity !== null) {
            $weights = $targetWordCharacterSimilarity !== null
                ? ['text' => 0.25, 'target_phoneme' => 0.50, 'target_character' => 0.25]
                : ['text' => 0.30, 'target_phoneme' => 0.70, 'target_character' => 0.0];

            $accuracy = (int) round(
                ($textAccuracy * $weights['text'])
                + ($targetWordPhonemeSimilarity * $weights['target_phoneme'])
                + (($targetWordCharacterSimilarity ?? 0) * $weights['target_character'])
            );
        } elseif ($phonemeSimilarity !== null) {
            $weights = $characterSimilarity !== null
                ? ['text' => 0.35, 'phoneme' => 0.45, 'character' => 0.20]
                : ['text' => 0.40, 'phoneme' => 0.60, 'character' => 0.0];

            $accuracy = (int) round(
                ($textAccuracy * $weights['text'])
                + ($phonemeSimilarity * $weights['phoneme'])
                + (($characterSimilarity ?? 0) * $weights['character'])
            );
        } elseif ($characterSimilarity !== null) {
            $accuracy = (int) round(($textAccuracy * 0.55) + ($characterSimilarity * 0.45));
        }

        if (! $hasPronunciationSignals && ! $hasTargetWordSignals && $accuracy > 85) {
            // Without phoneme/pronunciation evidence, do not let a clean
            // transcript alone impersonate a pronunciation-perfect score.
            $accuracy = 85;
        }

        if ($textAccuracy === 100) {
            // If every word matched, keep the score in a child-friendly band.
            // Pronunciation quality can still distinguish strong vs weaker reads,
            // but it should not drag a perfect word match into failing territory.
            $accuracy = max(80, $accuracy);
        }

        $wordsPerSecond = ($durationSeconds && $durationSeconds > 0)
            ? $totalWords / $durationSeconds
            : null;

        $partialButPronouncedClearly = $matchedWords > 0
            && $matchedWords < $totalWords
            && $textAccuracy < 50
            && $strongPronunciationSignal >= 85;

        $isRushed = ! $exactSentenceMatch
            && (
                $compactSentenceMatch
                || ($wordsPerSecond !== null && $wordsPerSecond > 3.5 && $textAccuracy >= 80)
                || $partialButPronouncedClearly
            );

        $isSlow = ! $exactSentenceMatch
            && ! $isRushed
            && $wordsPerSecond !== null
            && $wordsPerSecond < 1.1
            && $textAccuracy >= 60;

        if ($partialButPronouncedClearly) {
            // If we clearly heard part of the sentence and the pronunciation
            // signal is strong, be gentler: this usually means the learner
            // said it fast or the recognizer only captured part of the read.
            $accuracy = max(
                $accuracy,
                min(82, (int) round(($textAccuracy * 0.3) + ($strongPronunciationSignal * 0.45) + 28))
            );
        }

        $pronunciationUnclear = $missingWords === 0
            && (($targetWordPhonemeSimilarity !== null && $targetWordPhonemeSimilarity < 80)
                || ($phonemeSimilarity !== null && $phonemeSimilarity < 75))
            && $textAccuracy >= 80;

        $feedbackLabel = match (true) {
            ! $hasPronunciationSignals && ! $hasTargetWordSignals && $textAccuracy >= 90 => self::TRANSCRIPT_MATCHED,
            $pronunciationUnclear => self::RIGHT_WORDS_BUT_UNCLEAR,
            $isRushed => self::A_LITTLE_RUSHED,
            $isSlow => self::A_LITTLE_SLOW,
            $exactSentenceMatch || $accuracy >= 90 => self::MOSTLY_CORRECT,
            $missingWords === 1 => self::MISSING_ONE_WORD,
            default => self::RIGHT_WORDS_BUT_UNCLEAR,
        };

        return [
            'accuracy_percentage' => $accuracy,
            'score_ten' => round($accuracy / 10, 1),
            'text_accuracy_percentage' => $textAccuracy,
            'matched_words' => $matchedWords,
            'total_words' => $totalWords,
            'missing_words' => $missingWords,
            'feedback_label' => $feedbackLabel,
            'is_rushed' => $isRushed,
            'is_slow' => $isSlow,
            'character_similarity_percentage' => $characterSimilarity,
            'phoneme_similarity_percentage' => $phonemeSimilarity,
            'target_word_character_similarity_percentage' => $targetWordCharacterSimilarity,
            'target_word_phoneme_similarity_percentage' => $targetWordPhonemeSimilarity,
            'target_word' => trim((string) ($aiSignals['target_word'] ?? '')) ?: null,
            'actual_target_word' => trim((string) ($aiSignals['actual_target_word'] ?? '')) ?: null,
            'target_word_error_type' => trim((string) ($aiSignals['target_word_error_type'] ?? '')) ?: null,
            'pronunciation_unclear' => $pronunciationUnclear,
            'pronunciation_verified' => $hasPronunciationSignals || $hasTargetWordSignals,
        ];
    }

    public function summarize(array $evaluations): array
    {
        if ($evaluations === []) {
            return [
                'average_accuracy_percentage' => 0,
                'task_score' => 0,
                'feedback_label' => self::RIGHT_WORDS_BUT_UNCLEAR,
            ];
        }

        $count = count($evaluations);
        $dominantThreshold = max(2, (int) ceil($count * 0.5));
        $averageAccuracy = (int) round(collect($evaluations)->avg('accuracy_percentage') ?? 0);
        $labelCounts = collect($evaluations)
            ->map(fn (array $evaluation) => $evaluation['feedback_label'] ?? self::RIGHT_WORDS_BUT_UNCLEAR)
            ->countBy();

        $feedbackLabel = match (true) {
            $averageAccuracy >= 90 => self::MOSTLY_CORRECT,
            ($labelCounts[self::TRANSCRIPT_MATCHED] ?? 0) >= $dominantThreshold => self::TRANSCRIPT_MATCHED,
            ($labelCounts[self::A_LITTLE_RUSHED] ?? 0) >= $dominantThreshold => self::A_LITTLE_RUSHED,
            ($labelCounts[self::A_LITTLE_SLOW] ?? 0) >= $dominantThreshold => self::A_LITTLE_SLOW,
            ($labelCounts[self::MISSING_ONE_WORD] ?? 0) >= $dominantThreshold => self::MISSING_ONE_WORD,
            ($labelCounts[self::RIGHT_WORDS_BUT_UNCLEAR] ?? 0) >= $dominantThreshold => self::RIGHT_WORDS_BUT_UNCLEAR,
            default => self::MIXED_RESULTS,
        };

        return [
            'average_accuracy_percentage' => $averageAccuracy,
            'task_score' => max(0, min(10, (int) round($averageAccuracy / 10))),
            'feedback_label' => $feedbackLabel,
        ];
    }

    private function normalizedWords(string $text): array
    {
        $normalized = preg_replace('/[^a-z0-9\\s]+/i', ' ', strtolower($text)) ?? '';
        $normalized = preg_replace('/\\s+/', ' ', trim($normalized)) ?? '';

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    private function compactForm(string $text): string
    {
        $normalized = preg_replace('/[^a-z0-9]+/i', '', strtolower($text)) ?? '';

        return trim($normalized);
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
                $cost = $expectedWords[$i - 1] === $actualWords[$j - 1] ? 0 : 1;
                $distances[$i][$j] = min(
                    $distances[$i - 1][$j] + 1,
                    $distances[$i][$j - 1] + 1,
                    $distances[$i - 1][$j - 1] + $cost,
                );
            }
        }

        return $distances[$expectedCount][$actualCount];
    }

    private function similarityPercentage(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) round(max(0, min(1, (float) $value)) * 100);
    }
}
