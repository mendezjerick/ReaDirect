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

    public function align(string $expectedSentence, string $actualTranscript): array
    {
        $expectedWords = $this->normalizedWords($expectedSentence);
        $actualWords = $this->normalizedWords($actualTranscript);
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

        $path = [];
        $i = $expectedCount;
        $j = $actualCount;
        $substitutions = 0;
        $deletions = 0;
        $insertions = 0;
        $correct = 0;

        while ($i > 0 || $j > 0) {
            if (
                $i > 0
                && $j > 0
                && $expectedWords[$i - 1] === $actualWords[$j - 1]
                && $distances[$i][$j] === $distances[$i - 1][$j - 1]
            ) {
                $path[] = [
                    'operation' => 'match',
                    'expected' => $expectedWords[$i - 1],
                    'actual' => $actualWords[$j - 1],
                ];
                $correct++;
                $i--;
                $j--;

                continue;
            }

            if (
                $i > 0
                && $j > 0
                && $distances[$i][$j] === $distances[$i - 1][$j - 1] + 1
            ) {
                $path[] = [
                    'operation' => 'substitution',
                    'expected' => $expectedWords[$i - 1],
                    'actual' => $actualWords[$j - 1],
                ];
                $substitutions++;
                $i--;
                $j--;

                continue;
            }

            if ($i > 0 && $distances[$i][$j] === $distances[$i - 1][$j] + 1) {
                $path[] = [
                    'operation' => 'deletion',
                    'expected' => $expectedWords[$i - 1],
                    'actual' => null,
                ];
                $deletions++;
                $i--;

                continue;
            }

            $path[] = [
                'operation' => 'insertion',
                'expected' => null,
                'actual' => $actualWords[$j - 1],
            ];
            $insertions++;
            $j--;
        }

        $wer = $expectedCount > 0
            ? round(($substitutions + $deletions + $insertions) / $expectedCount, 4)
            : null;

        return [
            'expected_words' => $expectedWords,
            'actual_words' => $actualWords,
            'substitutions' => $substitutions,
            'deletions' => $deletions,
            'insertions' => $insertions,
            'correct' => $correct,
            'total_expected_words' => $expectedCount,
            'wer' => $wer,
            'accuracy_percentage' => $wer === null ? 0 : (int) round(max(0, 1 - $wer) * 100),
            'alignment' => array_reverse($path),
        ];
    }

    public function evaluate(string $expectedSentence, string $actualTranscript, ?float $durationSeconds = null, ?array $aiSignals = null, ?array $timingTargets = null): array
    {
        $aiSignals ??= [];
        $timingTargets = $this->timingTargets($timingTargets ?? $aiSignals['timing_targets'] ?? []);
        $alignment = $this->align($expectedSentence, $actualTranscript);
        $alignment = $this->alignmentFromAiSignals($expectedSentence, $actualTranscript, $aiSignals) ?? $alignment;
        $expectedWords = $alignment['expected_words'];
        $actualWords = $alignment['actual_words'];
        $warnings = [];
        $retry = $this->retryMetadata($aiSignals);

        if ($expectedWords === []) {
            return [
                'accuracy_percentage' => 0,
                'score_ten' => 0.0,
                'matched_words' => 0,
                'total_words' => 0,
                'total_words_read' => 0,
                'errors' => 0,
                'word_errors' => 0,
                'missing_words' => 0,
                'correct_words' => 0,
                'total_expected_words' => 0,
                'substitutions' => 0,
                'deletions' => 0,
                'insertions' => 0,
                'wer' => null,
                'wer_accuracy_percentage' => 0,
                'alignment' => [],
                'wpm' => null,
                'wcpm' => null,
                'duration_seconds' => $durationSeconds,
                'words_per_second' => null,
                'pace_label' => 'unknown',
                'pacing_label' => 'unknown',
                'pacing_warning' => 'Reading duration is missing, so pacing could not be calculated.',
                ...$this->timingTargetFields($timingTargets),
                'feedback_label' => self::RIGHT_WORDS_BUT_UNCLEAR,
                'is_rushed' => false,
                'is_slow' => false,
                'rushed' => false,
                'slow' => false,
                'pause_metrics' => null,
                'pause_metrics_available' => false,
                'pause_score' => 100,
                'long_pause_warning' => null,
                'fluency_score' => null,
                'fluency_label' => 'unknown',
                'fluency_components' => $this->emptyFluencyComponents(),
                'warnings' => ['Expected sentence has no words after normalization.'],
                ...$retry,
            ];
        }

        $totalWords = count($expectedWords);
        $errors = max(0, (int) $alignment['substitutions'] + (int) $alignment['deletions'] + (int) $alignment['insertions']);
        $correctWords = max($totalWords - $errors, 0);
        $matchedWords = $alignment['correct'];
        $missingWords = $alignment['deletions'];
        $wer = $alignment['wer'];

        $normalizedExpected = implode(' ', $expectedWords);
        $normalizedActual = implode(' ', $actualWords);
        $compactExpected = $this->compactForm($expectedSentence);
        $compactActual = $this->compactForm($actualTranscript);

        $exactSentenceMatch = $normalizedExpected !== '' && $normalizedExpected === $normalizedActual;
        $compactSentenceMatch = $compactExpected !== '' && $compactExpected === $compactActual;

        $textAccuracy = (int) round(max(0, 1 - (float) $wer) * 100);

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

        $timing = $this->timingMetrics($actualWords, $correctWords, $durationSeconds, $errors, $totalWords);
        $wordsPerSecond = $timing['words_per_second'];
        $warnings = array_merge($warnings, $timing['warnings']);

        $pacing = $this->pacingMetrics($durationSeconds, $timingTargets, $wordsPerSecond, $textAccuracy, $compactSentenceMatch && ! $exactSentenceMatch);

        $partialButPronouncedClearly = $matchedWords > 0
            && $matchedWords < $totalWords
            && $textAccuracy < 50
            && $strongPronunciationSignal >= 85;

        $isRushed = $pacing['rushed'] || $partialButPronouncedClearly;

        $isSlow = ! $exactSentenceMatch
            && ! $isRushed
            && $pacing['slow']
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

        $pause = $this->pauseMetrics($aiSignals['pause_metrics'] ?? null);
        $fluency = $this->fluencyMetrics(
            wcpm: $timing['wcpm'],
            wpm: $timing['wpm'],
            accuracyPercentage: $accuracy,
            pauseScore: $pause['pause_score'],
            pauseMetricsAvailable: $pause['pause_metrics_available'],
            pacingLabel: $pacing['pace_label'],
            completionScore: $this->completionScore($totalWords, $alignment['deletions']),
            retryRequired: $retry['retry_required'],
        );

        return [
            'accuracy_percentage' => $accuracy,
            'score_ten' => round($accuracy / 10, 1),
            'text_accuracy_percentage' => $textAccuracy,
            'matched_words' => $matchedWords,
            'total_words' => $totalWords,
            'total_words_read' => $totalWords,
            'errors' => $errors,
            'word_errors' => $errors,
            'missing_words' => $missingWords,
            'correct_words' => $correctWords,
            'total_expected_words' => $totalWords,
            'expected_words' => $expectedWords,
            'actual_words' => $actualWords,
            'substitutions' => $alignment['substitutions'],
            'deletions' => $alignment['deletions'],
            'insertions' => $alignment['insertions'],
            'wer' => $wer,
            'wer_accuracy_percentage' => $textAccuracy,
            'alignment' => $alignment['alignment'],
            'cer' => $aiSignals['corrected_cer'] ?? $aiSignals['raw_cer'] ?? null,
            'wpm' => $timing['wpm'],
            'wcpm' => $timing['wcpm'],
            'duration_seconds' => $timing['duration_seconds'],
            'words_per_second' => $wordsPerSecond,
            'pace_label' => $pacing['pace_label'],
            'pacing_label' => $pacing['pace_label'],
            'pacing_warning' => $pacing['pacing_warning'],
            ...$this->timingTargetFields($timingTargets),
            'feedback_label' => $feedbackLabel,
            'is_rushed' => $isRushed,
            'is_slow' => $isSlow,
            'rushed' => $isRushed,
            'slow' => $isSlow,
            'pause_metrics' => $pause['pause_metrics'],
            'pause_metrics_available' => $pause['pause_metrics_available'],
            'pause_score' => $pause['pause_score'],
            'long_pause_warning' => $pause['long_pause_warning'],
            'fluency_score' => $fluency['fluency_score'],
            'fluency_label' => $fluency['fluency_label'],
            'fluency_components' => $fluency['fluency_components'],
            'fluency_weights' => $fluency['fluency_weights'],
            'character_similarity_percentage' => $characterSimilarity,
            'phoneme_similarity_percentage' => $phonemeSimilarity,
            'target_word_character_similarity_percentage' => $targetWordCharacterSimilarity,
            'target_word_phoneme_similarity_percentage' => $targetWordPhonemeSimilarity,
            'target_word' => trim((string) ($aiSignals['target_word'] ?? '')) ?: null,
            'actual_target_word' => trim((string) ($aiSignals['actual_target_word'] ?? '')) ?: null,
            'target_word_error_type' => trim((string) ($aiSignals['target_word_error_type'] ?? '')) ?: null,
            'pronunciation_unclear' => $pronunciationUnclear,
            'pronunciation_verified' => $hasPronunciationSignals || $hasTargetWordSignals,
            'warnings' => $warnings,
            ...$retry,
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

    public function normalizedWords(string $text): array
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

    private function timingMetrics(array $actualWords, int $correctWords, ?float $durationSeconds, int $errors, int $totalWordsRead): array
    {
        if (! $durationSeconds || $durationSeconds <= 0) {
            return [
                'wpm' => null,
                'wcpm' => null,
                'duration_seconds' => $durationSeconds,
                'words_per_second' => null,
                'warnings' => ['Reading duration is missing or invalid, so WPM and WCPM were not calculated.'],
            ];
        }

        $minutes = $durationSeconds / 60;
        $actualWordCount = count($actualWords);
        $formulaCorrectWords = max($totalWordsRead - $errors, 0);

        return [
            'wpm' => round($actualWordCount / $minutes, 2),
            'wcpm' => round($formulaCorrectWords / $minutes, 2),
            'duration_seconds' => round($durationSeconds, 3),
            'words_per_second' => round($actualWordCount / $durationSeconds, 2),
            'warnings' => [],
        ];
    }

    private function pacingMetrics(?float $durationSeconds, array $timingTargets, ?float $wordsPerSecond, int $textAccuracy, bool $compactSentenceMatch): array
    {
        if (! $durationSeconds || $durationSeconds <= 0) {
            return [
                'rushed' => false,
                'slow' => false,
                'pace_label' => 'unknown',
                'pacing_warning' => 'Reading duration is missing, so pacing could not be calculated.',
            ];
        }

        if (($timingTargets['min_fluent_time_seconds'] ?? null) !== null && ($timingTargets['max_fluent_time_seconds'] ?? null) !== null) {
            $min = (float) $timingTargets['min_fluent_time_seconds'];
            $max = (float) $timingTargets['max_fluent_time_seconds'];

            if ($durationSeconds < $min) {
                return [
                    'rushed' => true,
                    'slow' => false,
                    'pace_label' => 'too_fast',
                    'pacing_warning' => 'Slow down a little so each word is clear.',
                ];
            }

            if ($durationSeconds > $max) {
                return [
                    'rushed' => false,
                    'slow' => true,
                    'pace_label' => 'too_slow',
                    'pacing_warning' => 'Try reading it a little smoother without long pauses.',
                ];
            }

            return [
                'rushed' => false,
                'slow' => false,
                'pace_label' => 'fluent',
                'pacing_warning' => 'Great pace. You read it clearly and smoothly.',
            ];
        }

        if ($wordsPerSecond === null) {
            return [
                'rushed' => false,
                'slow' => false,
                'pace_label' => 'unknown',
                'pacing_warning' => 'Reading duration is missing, so pacing could not be calculated.',
            ];
        }

        $fastThreshold = (float) $this->setting('readirect_ai.sentence_fluency.pacing.too_fast_wps', 3.5);
        $slowThreshold = (float) $this->setting('readirect_ai.sentence_fluency.pacing.too_slow_wps', 1.1);
        $rushed = ($wordsPerSecond > $fastThreshold && $textAccuracy >= 80) || $compactSentenceMatch;
        $slow = $wordsPerSecond < $slowThreshold && $textAccuracy >= 60;

        if ($rushed) {
            return [
                'rushed' => true,
                'slow' => false,
                'pace_label' => 'too_fast',
                'pacing_warning' => 'Slow down a little so each word is clear.',
            ];
        }

        if ($slow) {
            return [
                'rushed' => false,
                'slow' => true,
                'pace_label' => 'too_slow',
                'pacing_warning' => 'Try reading it a little smoother without long pauses.',
            ];
        }

        return [
            'rushed' => false,
            'slow' => false,
            'pace_label' => 'fluent',
            'pacing_warning' => 'Great pace. You read it clearly and smoothly.',
        ];
    }

    private function pauseMetrics(mixed $pauseMetrics): array
    {
        if (! is_array($pauseMetrics)) {
            return [
                'pause_metrics' => null,
                'pause_metrics_available' => false,
                'pause_score' => 100,
                'long_pause_warning' => null,
            ];
        }

        $longPauseCount = max(0, (int) ($pauseMetrics['long_pause_count'] ?? 0));
        $veryLongPauseCount = max(0, (int) ($pauseMetrics['very_long_pause_count'] ?? 0));
        $pauseRatio = is_numeric($pauseMetrics['pause_ratio'] ?? null) ? (float) $pauseMetrics['pause_ratio'] : 0.0;
        $baseScores = $this->setting('readirect_ai.sentence_fluency.pause.long_pause_scores', [
            0 => 100,
            1 => 85,
            2 => 70,
            3 => 50,
        ]);

        $score = (int) ($baseScores[min($longPauseCount, 3)] ?? 50);
        $score -= $veryLongPauseCount * (int) $this->setting('readirect_ai.sentence_fluency.pause.very_long_pause_penalty', 20);

        if ($pauseRatio > (float) $this->setting('readirect_ai.sentence_fluency.pause.high_pause_ratio', 0.35)) {
            $score -= (int) $this->setting('readirect_ai.sentence_fluency.pause.high_pause_ratio_penalty', 15);
        }

        $score = max(0, min(100, $score));
        $warning = match (true) {
            $veryLongPauseCount > 0 => 'There was a very long pause. Try reading the sentence more continuously.',
            $longPauseCount > 1 => 'There were '.$longPauseCount.' long pauses. Try reading the sentence more continuously.',
            $longPauseCount === 1 => 'There was 1 long pause. Try reading the sentence more continuously.',
            default => null,
        };

        return [
            'pause_metrics' => $pauseMetrics,
            'pause_metrics_available' => true,
            'pause_score' => $score,
            'long_pause_warning' => $warning,
        ];
    }

    private function fluencyMetrics(
        ?float $wcpm,
        ?float $wpm,
        int $accuracyPercentage,
        int $pauseScore,
        bool $pauseMetricsAvailable,
        string $pacingLabel,
        int $completionScore,
        bool $retryRequired
    ): array {
        $components = [
            'wcpm' => $wcpm,
            'wpm' => $wpm,
            'accuracy_percentage' => max(0, min(100, $accuracyPercentage)),
            'pace_label' => $pacingLabel,
            'pause_score' => $pauseScore,
            'completion_score' => $completionScore,
        ];

        return [
            'fluency_score' => $wcpm,
            'fluency_label' => $retryRequired ? 'retry_needed' : $pacingLabel,
            'fluency_components' => $components,
            'fluency_weights' => ['metric' => 'wcpm_only', 'pacing' => 'separate_feedback_signal'],
            'pause_metrics_available' => $pauseMetricsAvailable,
            'wpm' => $wpm,
        ];
    }

    private function completionScore(int $totalWords, int $deletions): int
    {
        if ($totalWords <= 0) {
            return 0;
        }

        return (int) round(max(0, ($totalWords - $deletions) / $totalWords) * 100);
    }

    private function retryMetadata(array $aiSignals): array
    {
        return [
            'retry_required' => (bool) ($aiSignals['retry_required'] ?? false),
            'uncertain' => (bool) ($aiSignals['uncertain'] ?? false),
            'uncertainty_reasons' => array_values((array) ($aiSignals['uncertainty_reasons'] ?? [])),
            'audio_quality' => $aiSignals['audio_quality'] ?? null,
            'learner_retry_message' => $aiSignals['learner_retry_message'] ?? null,
        ];
    }

    private function emptyFluencyComponents(): array
    {
        return [
            'wcpm' => null,
            'wpm' => null,
            'accuracy_percentage' => 0,
            'pace_label' => 'unknown',
            'pause_score' => 100,
            'completion_score' => 0,
        ];
    }

    private function timingTargets(array $targets): array
    {
        return [
            'target_read_time_seconds' => $this->nullableFloat($targets['target_read_time_seconds'] ?? null),
            'min_fluent_time_seconds' => $this->nullableFloat($targets['min_fluent_time_seconds'] ?? null),
            'max_fluent_time_seconds' => $this->nullableFloat($targets['max_fluent_time_seconds'] ?? null),
            'target_wcpm' => $this->nullableFloat($targets['target_wcpm'] ?? null),
            'min_expected_wcpm' => $this->nullableFloat($targets['min_expected_wcpm'] ?? null),
            'max_expected_wcpm' => $this->nullableFloat($targets['max_expected_wcpm'] ?? null),
            'pace_feedback_rule' => $this->nullableText($targets['pace_feedback_rule'] ?? null),
            'pace_mastery_required' => $this->nullableBool($targets['pace_mastery_required'] ?? null),
        ];
    }

    private function timingTargetFields(array $targets): array
    {
        return array_filter($targets, static fn ($value): bool => $value !== null);
    }

    private function setting(string $key, mixed $default): mixed
    {
        try {
            if (function_exists('config')) {
                $value = config($key);

                return $value === null ? $default : $value;
            }
        } catch (\Throwable) {
            return $default;
        }

        return $default;
    }

    private function similarityPercentage(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) round(max(0, min(1, (float) $value)) * 100);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function alignmentFromAiSignals(string $expectedSentence, string $actualTranscript, array $aiSignals): ?array
    {
        $wordAlignment = $aiSignals['word_alignment'] ?? null;

        if (! is_array($wordAlignment) || $wordAlignment === []) {
            return null;
        }

        $expectedEntries = array_values(array_filter(
            $wordAlignment,
            static fn ($item): bool => is_array($item) && array_key_exists('expected_word', $item) && $item['expected_word'] !== null
        ));

        if ($expectedEntries === []) {
            return null;
        }

        $expectedWords = $this->normalizedWords($expectedSentence);
        $actualWords = $this->normalizedWords($actualTranscript);

        if (count($expectedEntries) !== count($expectedWords)) {
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
        $path = [];
        $correct = 0;
        $substitutions = 0;
        $deletions = 0;
        $insertions = count(array_filter(
            $wordAlignment,
            static fn ($item): bool => is_array($item) && ($item['status'] ?? null) === 'inserted'
        ));

        foreach ($expectedEntries as $index => $item) {
            $status = (string) ($item['status'] ?? 'incorrect');
            $isCorrect = (bool) ($item['counts_as_correct'] ?? false) || in_array($status, $acceptedStatuses, true);

            if ($isCorrect) {
                $correct++;
                $operation = $status === 'exact_correct' ? 'match' : 'accepted';
            } elseif ($status === 'missing') {
                $deletions++;
                $operation = 'deletion';
            } else {
                $substitutions++;
                $operation = $status === 'partial' ? 'partial' : 'substitution';
            }

            $path[] = [
                'operation' => $operation,
                'expected' => $expectedWords[$index] ?? $item['expected_word'],
                'actual' => $item['recognized_word'] ?? null,
                'status' => $status,
                'chunk_match_id' => $item['chunk_match_id'] ?? null,
            ];
        }

        $expectedCount = count($expectedWords);
        $wer = $expectedCount > 0
            ? round(($substitutions + $deletions + $insertions) / $expectedCount, 4)
            : null;

        return [
            'expected_words' => $expectedWords,
            'actual_words' => $actualWords,
            'substitutions' => $substitutions,
            'deletions' => $deletions,
            'insertions' => $insertions,
            'correct' => $correct,
            'total_expected_words' => $expectedCount,
            'wer' => $wer,
            'accuracy_percentage' => $wer === null ? 0 : (int) round(max(0, 1 - $wer) * 100),
            'alignment' => $path,
        ];
    }
}
