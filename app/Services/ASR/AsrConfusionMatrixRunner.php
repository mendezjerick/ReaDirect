<?php

namespace App\Services\ASR;

use App\Services\AI\ReadirectAIService;
use Illuminate\Support\Str;

class AsrConfusionMatrixRunner
{
    public function __construct(
        private readonly AsrConfusionFixtureService $fixtures,
        private readonly ReadirectAIService $ai,
        private readonly AsrResponseNormalizer $normalizer,
    ) {
    }

    public function run(?string $category = null, ?int $limit = null, bool $save = true): array
    {
        $manifest = $this->fixtures->loadManifest();
        $rows = [];
        $category = $this->normalizeCategory($category);

        foreach ($manifest['fixtures'] ?? [] as $fixture) {
            if ($category !== null && ($fixture['category'] ?? null) !== $category) {
                continue;
            }

            $rows[] = $this->runFixture($fixture);

            if ($limit !== null && count($rows) >= $limit) {
                break;
            }
        }

        $run = [
            'run_id' => 'asr_confusion_'.now()->format('Ymd_His'),
            'ran_at' => now()->toISOString(),
            'manifest_path' => $this->fixtures->manifestPath(),
            'manifest_generated_at' => $manifest['generated_at'] ?? null,
            'total_tested_recordings' => count($rows),
            'summary' => $this->aggregate($rows),
            'rows' => $rows,
        ];

        if ($save) {
            $this->fixtures->saveRun($run);
        }

        return $run;
    }

    public function runFixture(array $fixture): array
    {
        $audioPath = $this->fixtures->absoluteAudioPath($fixture);
        $payload = $this->payload($fixture, $audioPath);
        $aiResponse = is_file($audioPath)
            ? $this->ai->analyzeAudio($payload)
            : [
                'ok' => false,
                'error' => 'fixture_audio_missing',
                'warnings' => ['Fixture audio file is missing: '.$audioPath],
            ];
        $normalized = $this->normalizer->normalize(is_array($aiResponse) ? $aiResponse : []);
        $context = $this->context($fixture);
        $resolved = [
            'transcript' => $normalized['scoring_transcript'],
            'displayed_transcript' => $normalized['display_transcript'],
            'source' => 'ai_asr',
            'confidence' => $aiResponse['confidence'] ?? null,
            'ai_response' => is_array($aiResponse) ? $aiResponse : [],
        ];
        $recordingAccepted = $this->normalizer->canComplete($resolved, $context);
        $scoredCorrect = $this->scoredCorrect(is_array($aiResponse) ? $aiResponse : [], $normalized);
        $expectedCorrect = (bool) ($fixture['expected_correct'] ?? false);
        $expectedValid = (bool) ($fixture['expected_recording_valid'] ?? true);
        $classification = $this->classify($expectedValid, $expectedCorrect, $recordingAccepted, $scoredCorrect, (string) ($fixture['fixture_type'] ?? ''));

        return [
            'category' => $fixture['category'] ?? null,
            'task' => $fixture['task'] ?? null,
            'task_label' => $fixture['task_label'] ?? null,
            'item_key' => $fixture['item_key'] ?? null,
            'expected_answer' => $fixture['expected_answer'] ?? null,
            'fixture_type' => $fixture['fixture_type'] ?? null,
            'spoken_text' => $fixture['spoken_text'] ?? null,
            'audio_file_path' => $fixture['audio_file_path'] ?? null,
            'recording_accepted' => $recordingAccepted,
            'asr_raw_output' => $normalized['debug_transcript'],
            'normalized_output' => $normalized['scoring_transcript'],
            'displayed_output' => $normalized['display_transcript'],
            'final_correctness_result' => $scoredCorrect,
            'expected_correctness' => $expectedCorrect,
            'expected_recording_valid' => $expectedValid,
            'confusion_matrix_result' => $classification['result'],
            'failure_reason' => $classification['failure_reason'],
            'wrong_audible_rejected_as_invalid' => $classification['wrong_audible_rejected_as_invalid'],
            'retry_required' => $normalized['retry_required'],
            'uncertain' => $normalized['uncertain'],
            'quality_gate_failed' => (bool) ($aiResponse['quality_gate_failed'] ?? false),
            'learner_retry_message' => (string) ($aiResponse['learner_retry_message'] ?? ''),
            'audio_quality' => $aiResponse['audio_quality'] ?? [],
            'scoring_debug' => $this->debugFields(is_array($aiResponse) ? $aiResponse : [], $normalized),
            'request_payload' => $payload,
            'ai_ok' => (bool) ($aiResponse['ok'] ?? false),
            'ai_error' => $aiResponse['error'] ?? null,
            'ai_warnings' => $aiResponse['warnings'] ?? [],
        ];
    }

    private function payload(array $fixture, string $audioPath): array
    {
        return [
            'audio_path' => $audioPath,
            'expected_text' => $fixture['expected_answer'] ?? null,
            'prompt_type' => $fixture['prompt_type'] ?? null,
            'accepted_answers' => array_values($fixture['accepted_answers'] ?? []),
            'prompt_id' => $fixture['item_key'] ?? null,
            'module_key' => $fixture['module_key'] ?? null,
            'module_type' => $fixture['module_key'] ?? null,
            'activity_type' => $fixture['activity_type'] ?? null,
            'assessment_type' => $fixture['assessment_type'] ?? null,
            'task_type' => $fixture['task_type'] ?? null,
            'item_id' => $fixture['item_key'] ?? null,
            'content_metadata' => [
                'asr_confusion_fixture' => true,
                'fixture_type' => $fixture['fixture_type'] ?? null,
                'spoken_text' => $fixture['spoken_text'] ?? null,
                'source_file' => $fixture['source_file'] ?? null,
                'prompt_text' => $fixture['prompt_text'] ?? null,
                'metadata' => $fixture['metadata'] ?? [],
            ],
            'debug' => true,
            'include_trace' => false,
            'debug_trace' => false,
        ];
    }

    private function context(array $fixture): array
    {
        return [
            'expected_text' => $fixture['expected_answer'] ?? null,
            'accepted_answers' => array_values($fixture['accepted_answers'] ?? []),
            'prompt_type' => $fixture['prompt_type'] ?? null,
            'task_type' => $fixture['task_type'] ?? null,
            'activity_type' => $fixture['activity_type'] ?? null,
        ];
    }

    private function scoredCorrect(array $aiResponse, array $normalized): bool
    {
        return (bool) (
            ($normalized['accepted'] ?? false)
            || ($aiResponse['is_correct'] ?? false)
            || ($aiResponse['is_accepted'] ?? false)
        );
    }

    private function classify(bool $expectedValid, bool $expectedCorrect, bool $recordingAccepted, bool $scoredCorrect, string $fixtureType): array
    {
        if (! $expectedValid) {
            $accepted = $recordingAccepted;

            return [
                'result' => $accepted ? 'invalid_audio_accepted' : 'invalid_audio_rejected',
                'failure_reason' => $accepted ? Str::upper($fixtureType).'_INCORRECTLY_ACCEPTED' : null,
                'wrong_audible_rejected_as_invalid' => false,
            ];
        }

        if ($expectedCorrect) {
            if (! $recordingAccepted) {
                return [
                    'result' => 'FN',
                    'failure_reason' => 'CORRECT_AUDIO_REJECTED_AS_INVALID',
                    'wrong_audible_rejected_as_invalid' => false,
                ];
            }

            return [
                'result' => $scoredCorrect ? 'TP' : 'FN',
                'failure_reason' => $scoredCorrect ? null : 'CORRECT_AUDIO_SCORED_INCORRECT',
                'wrong_audible_rejected_as_invalid' => false,
            ];
        }

        if (! $recordingAccepted) {
            return [
                'result' => 'wrong_audio_rejected',
                'failure_reason' => 'WRONG_AUDIBLE_AUDIO_REJECTED_AS_INVALID',
                'wrong_audible_rejected_as_invalid' => true,
            ];
        }

        return [
            'result' => $scoredCorrect ? 'FP' : 'TN',
            'failure_reason' => $scoredCorrect ? 'WRONG_AUDIO_SCORED_CORRECT' : null,
            'wrong_audible_rejected_as_invalid' => false,
        ];
    }

    private function aggregate(array $rows): array
    {
        $overall = $this->emptyBucket();
        $byCategory = [];
        $byTask = [];
        $byItem = [];

        foreach ($rows as $row) {
            $this->addToBucket($overall, $row);

            $category = (string) ($row['category'] ?? 'unknown');
            $task = $category.'/'.(string) ($row['task'] ?? 'unknown');
            $item = $task.'/'.(string) ($row['item_key'] ?? 'unknown');

            $byCategory[$category] ??= $this->emptyBucket($category);
            $byTask[$task] ??= $this->emptyBucket($task);
            $byItem[$item] ??= $this->emptyBucket($item);

            $this->addToBucket($byCategory[$category], $row);
            $this->addToBucket($byTask[$task], $row);
            $this->addToBucket($byItem[$item], $row);
        }

        return [
            'overall' => $this->finalizeBucket($overall),
            'by_category' => array_map(fn (array $bucket) => $this->finalizeBucket($bucket), $byCategory),
            'by_task' => array_map(fn (array $bucket) => $this->finalizeBucket($bucket), $byTask),
            'by_item' => array_map(fn (array $bucket) => $this->finalizeBucket($bucket), $byItem),
        ];
    }

    private function emptyBucket(?string $key = null): array
    {
        return [
            'key' => $key,
            'total' => 0,
            'TP' => 0,
            'TN' => 0,
            'FP' => 0,
            'FN' => 0,
            'valid_audible_wrong_accepted' => 0,
            'valid_audible_wrong_incorrectly_rejected' => 0,
            'silence_rejected' => 0,
            'low_volume_rejected' => 0,
            'silence_incorrectly_accepted' => 0,
            'low_volume_incorrectly_accepted' => 0,
            'invalid_audio_rejected' => 0,
            'invalid_audio_accepted' => 0,
            'wrong_audible_rejected_as_invalid' => 0,
        ];
    }

    private function addToBucket(array &$bucket, array $row): void
    {
        $bucket['total']++;
        $result = (string) ($row['confusion_matrix_result'] ?? '');

        if (in_array($result, ['TP', 'TN', 'FP', 'FN'], true)) {
            $bucket[$result]++;
        }

        $fixtureType = (string) ($row['fixture_type'] ?? '');
        $expectedValid = (bool) ($row['expected_recording_valid'] ?? false);
        $expectedCorrect = (bool) ($row['expected_correctness'] ?? false);
        $recordingAccepted = (bool) ($row['recording_accepted'] ?? false);

        if ($expectedValid && ! $expectedCorrect) {
            if ($recordingAccepted) {
                $bucket['valid_audible_wrong_accepted']++;
            } else {
                $bucket['valid_audible_wrong_incorrectly_rejected']++;
            }
        }

        if ($fixtureType === 'silence') {
            $recordingAccepted ? $bucket['silence_incorrectly_accepted']++ : $bucket['silence_rejected']++;
        }

        if ($fixtureType === 'low_volume') {
            $recordingAccepted ? $bucket['low_volume_incorrectly_accepted']++ : $bucket['low_volume_rejected']++;
        }

        if ($result === 'invalid_audio_rejected') {
            $bucket['invalid_audio_rejected']++;
        }

        if ($result === 'invalid_audio_accepted') {
            $bucket['invalid_audio_accepted']++;
        }

        if ((bool) ($row['wrong_audible_rejected_as_invalid'] ?? false)) {
            $bucket['wrong_audible_rejected_as_invalid']++;
        }
    }

    private function finalizeBucket(array $bucket): array
    {
        $tp = $bucket['TP'];
        $tn = $bucket['TN'];
        $fp = $bucket['FP'];
        $fn = $bucket['FN'];
        $matrixTotal = $tp + $tn + $fp + $fn;
        $precisionDenominator = $tp + $fp;
        $recallDenominator = $tp + $fn;
        $precision = $precisionDenominator > 0 ? $tp / $precisionDenominator : null;
        $recall = $recallDenominator > 0 ? $tp / $recallDenominator : null;

        return $bucket + [
            'matrix_total' => $matrixTotal,
            'accuracy' => $matrixTotal > 0 ? round(($tp + $tn) / $matrixTotal, 4) : null,
            'precision' => $precision !== null ? round($precision, 4) : null,
            'recall' => $recall !== null ? round($recall, 4) : null,
            'f1' => $precision !== null && $recall !== null && ($precision + $recall) > 0
                ? round((2 * $precision * $recall) / ($precision + $recall), 4)
                : null,
        ];
    }

    private function debugFields(array $aiResponse, array $normalized): array
    {
        return [
            'accepted' => $normalized['accepted'],
            'is_correct' => $aiResponse['is_correct'] ?? null,
            'is_accepted' => $aiResponse['is_accepted'] ?? null,
            'true_gop_score' => $normalized['overall_gop_score'] ?? $normalized['gop_score'] ?? null,
            'gop_score' => $normalized['gop_score'],
            'gop_decision' => $normalized['gop_decision'],
            'beam_search' => $aiResponse['beam_search'] ?? null,
            'decode_mode' => $aiResponse['decode_mode'] ?? null,
            'language_model_used' => $aiResponse['language_model_used'] ?? null,
            'expected_centric_score' => $normalized['composite_score'] ?? $aiResponse['composite_score'] ?? null,
            'threshold_used' => $aiResponse['threshold_used'] ?? null,
            'confidence_or_threshold_used' => $aiResponse['confidence_or_threshold_used'] ?? null,
            'correction_strategy_used' => $normalized['correction_strategy_used'],
            'dynamic_correction_applied' => $normalized['dynamic_correction_applied'],
            'dynamic_correction_confidence' => $normalized['dynamic_correction_confidence'],
            'asr_spelling_variant_applied' => $normalized['asr_spelling_variant_applied'],
            'word_alignment' => $normalized['word_alignment'],
        ];
    }

    private function normalizeCategory(?string $category): ?string
    {
        $category = trim((string) $category);

        if ($category === '') {
            return null;
        }

        return $category === 'module' ? 'modules' : $category;
    }
}
