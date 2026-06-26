<?php

namespace App\Services\ASR;

class AsrConfusionResultClassifier
{
    public function classify(bool $expectedShouldBeCorrect, bool $recordingAccepted, bool $finalCorrectness): array
    {
        if (! $recordingAccepted) {
            return [
                'code' => 'NA',
                'label' => 'Not Applicable',
                'result' => 'not_applicable',
                'recording_validity' => 'Invalid Audio',
                'recording_validity_code' => 'invalid_audio',
            ];
        }

        if ($expectedShouldBeCorrect && $finalCorrectness) {
            return $this->result('TP', 'True Positive', 'true_positive');
        }

        if (! $expectedShouldBeCorrect && ! $finalCorrectness) {
            return $this->result('TN', 'True Negative', 'true_negative');
        }

        if (! $expectedShouldBeCorrect && $finalCorrectness) {
            return $this->result('FP', 'False Positive', 'false_positive');
        }

        return $this->result('FN', 'False Negative', 'false_negative');
    }

    public function finalCorrectness(array $aiResponse, array $normalized): bool
    {
        return (bool) (
            ($normalized['accepted'] ?? false)
            || ($aiResponse['is_correct'] ?? false)
            || ($aiResponse['is_accepted'] ?? false)
        );
    }

    public function trueGopScore(array $aiResponse, array $normalized = []): ?float
    {
        foreach ([
            $normalized['overall_gop_score'] ?? null,
            $normalized['gop_score'] ?? null,
            $aiResponse['overall_gop_score'] ?? null,
            $aiResponse['gop_score'] ?? null,
            $aiResponse['true_gop'] ?? null,
            data_get($aiResponse, 'gop.score'),
            data_get($aiResponse, 'gop.overall'),
        ] as $value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    public function confidencePercent(?float $score): ?float
    {
        return $score === null ? null : round($score * 100, 1);
    }

    private function result(string $code, string $label, string $result): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'result' => $result,
            'recording_validity' => 'Valid Audio',
            'recording_validity_code' => 'valid_audio',
        ];
    }
}
