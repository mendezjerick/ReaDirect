<?php

namespace App\Services\ASR;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AsrConfusionManualHistoryService
{
    private const MAX_ROWS = 500;

    public function __construct(private readonly AsrConfusionFixtureService $fixtures)
    {
    }

    public function path(): string
    {
        return $this->fixtures->rootPath().DIRECTORY_SEPARATOR.'manual_history.json';
    }

    public function latest(?string $source = null, int $limit = 100): array
    {
        $rows = $this->load();
        $source = trim((string) $source);

        if ($source !== '') {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => ($row['source_mode'] ?? null) === $source
            ));
        }

        return array_slice(array_map(fn (array $row): array => $this->normalizeRow($row), $rows), 0, max(1, $limit));
    }

    public function append(array $row): array
    {
        $this->fixtures->ensureRoot();

        $row = [
            'id' => $row['id'] ?? (string) Str::uuid(),
            'created_at' => $row['created_at'] ?? now()->toISOString(),
            ...$row,
        ];

        $rows = $this->load();
        array_unshift($rows, $row);
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        File::put($this->path(), json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $row;
    }

    public function fromTrueSandbox(
        array $result,
        array $validated,
        array $classification,
        bool $recordingAccepted,
        bool $finalCorrectness,
        bool $expectedShouldBeCorrect,
        ?float $trueGopScore,
        ?User $user,
        ?string $audioFilePath
    ): array {
        $aiResponse = is_array($result['ai_response'] ?? null) ? $result['ai_response'] : [];
        $scoring = is_array($result['scoring'] ?? null) ? $result['scoring'] : [];

        return $this->append([
            'admin_user_id' => $user?->id,
            'source_mode' => 'true_sandbox',
            'source_label' => 'True Sandbox',
            'category' => $this->categoryFrom($validated),
            'task_or_module' => ($validated['module_key'] ?? null) ?: ($validated['task_type'] ?? $validated['section'] ?? null),
            'item_key' => $validated['item_id'] ?? null,
            'expected_answer' => $result['expected_text'] ?? $validated['expected_text'] ?? null,
            'expected_should_be_correct' => $expectedShouldBeCorrect,
            'expected_result_label' => $expectedShouldBeCorrect ? 'Correct Audio' : 'Wrong Audio',
            'raw_transcript' => $result['raw_transcript'] ?? null,
            'corrected_transcript' => $result['corrected_transcript'] ?? null,
            'displayed_transcript' => $result['displayed_transcript'] ?? null,
            'recording_validity' => $classification['recording_validity'],
            'recording_accepted' => $recordingAccepted,
            'final_correctness' => $finalCorrectness,
            'confusion_matrix_result' => $classification['label'],
            'confusion_matrix_code' => $classification['code'],
            'confidence_score' => $trueGopScore,
            'true_gop_score' => $trueGopScore,
            'expected_centric_score' => $result['composite_score'] ?? $aiResponse['composite_score'] ?? null,
            'beam_search_result' => $aiResponse['beam_search'] ?? $aiResponse['decode_mode'] ?? null,
            'phonetic_similarity' => $result['phonetic_similarity_score'] ?? null,
            'word_accuracy' => $scoring['word_accuracy'] ?? null,
            'strategy' => $result['correction_strategy_used'] ?? $result['dynamic_correction_strategy'] ?? $result['asr_spelling_variant_strategy'] ?? null,
            'reason' => $this->reasonFrom($classification, $expectedShouldBeCorrect, $finalCorrectness, $recordingAccepted, $result),
            'retry_or_uncertain' => [
                'retry_required' => (bool) ($result['retry_required'] ?? false),
                'uncertain' => (bool) ($result['uncertain'] ?? false),
            ],
            'audio_file_path' => $audioFilePath,
            'asr_response_json' => $result,
        ]);
    }

    public function fromManualFixture(array $result, ?User $user): array
    {
        $debug = is_array($result['scoring_debug'] ?? null) ? $result['scoring_debug'] : [];

        return $this->append([
            'admin_user_id' => $user?->id,
            'source_mode' => 'manual_fixture',
            'source_label' => 'Manual Fixture Test',
            'category' => $result['category'] ?? null,
            'task_or_module' => $result['task'] ?? null,
            'item_key' => $result['item_key'] ?? null,
            'expected_answer' => $result['expected_answer'] ?? null,
            'expected_should_be_correct' => (bool) ($result['expected_correctness'] ?? false),
            'expected_result_label' => ($result['expected_correctness'] ?? false) ? 'Correct Audio' : 'Wrong Audio',
            'raw_transcript' => $result['asr_raw_output'] ?? null,
            'corrected_transcript' => $result['normalized_output'] ?? null,
            'displayed_transcript' => $result['displayed_output'] ?? $result['normalized_output'] ?? null,
            'recording_validity' => ($result['recording_accepted'] ?? false) ? 'Valid Audio' : 'Invalid Audio',
            'recording_accepted' => (bool) ($result['recording_accepted'] ?? false),
            'final_correctness' => (bool) ($result['final_correctness_result'] ?? false),
            'confusion_matrix_result' => $this->labelFor((string) ($result['confusion_matrix_result'] ?? '')),
            'confusion_matrix_code' => (string) ($result['confusion_matrix_result'] ?? ''),
            'confidence_score' => $debug['true_gop_score'] ?? null,
            'true_gop_score' => $debug['true_gop_score'] ?? null,
            'expected_centric_score' => $debug['expected_centric_score'] ?? null,
            'beam_search_result' => $debug['beam_search'] ?? null,
            'phonetic_similarity' => $result['phonetic_similarity_score'] ?? null,
            'word_accuracy' => $debug['word_accuracy'] ?? null,
            'strategy' => $debug['correction_strategy_used'] ?? null,
            'reason' => $this->reasonFrom(
                [
                    'code' => (string) ($result['confusion_matrix_result'] ?? ''),
                    'label' => $this->labelFor((string) ($result['confusion_matrix_result'] ?? '')),
                    'recording_validity' => ($result['recording_accepted'] ?? false) ? 'Valid Audio' : 'Invalid Audio',
                ],
                (bool) ($result['expected_correctness'] ?? false),
                (bool) ($result['final_correctness_result'] ?? false),
                (bool) ($result['recording_accepted'] ?? false),
                $result,
            ),
            'retry_or_uncertain' => [
                'retry_required' => (bool) ($result['retry_required'] ?? false),
                'uncertain' => (bool) ($result['uncertain'] ?? false),
            ],
            'audio_file_path' => $result['audio_file_path'] ?? null,
            'asr_response_json' => $result,
        ]);
    }

    private function load(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($this->path()), true);

        return is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    private function categoryFrom(array $validated): string
    {
        $section = (string) ($validated['section'] ?? '');
        $assessmentType = (string) ($validated['assessment_type'] ?? '');

        if (str_starts_with($section, 'final_') || $assessmentType === 'final_reassessment') {
            return 'final';
        }

        if (str_starts_with($section, 'module_') || str_starts_with($assessmentType, 'module')) {
            return 'modules';
        }

        return str_starts_with($section, 'diagnostic_') || $assessmentType === 'diagnostic'
            ? 'diagnostic'
            : 'true_sandbox';
    }

    private function normalizeRow(array $row): array
    {
        $code = (string) ($row['confusion_matrix_code'] ?? $this->codeFor((string) ($row['confusion_matrix_result'] ?? '')));
        $expectedShouldBeCorrect = (bool) ($row['expected_should_be_correct'] ?? false);
        $finalCorrectness = (bool) ($row['final_correctness'] ?? false);
        $recordingAccepted = (bool) ($row['recording_accepted'] ?? (($row['recording_validity'] ?? '') === 'Valid Audio'));
        $classification = [
            'code' => $code,
            'label' => $this->labelFor($code),
            'recording_validity' => $row['recording_validity'] ?? ($recordingAccepted ? 'Valid Audio' : 'Invalid Audio'),
        ];
        $result = is_array($row['asr_response_json'] ?? null) ? $row['asr_response_json'] : [];

        $row['confusion_matrix_code'] = $code;
        $row['confusion_matrix_result'] = $classification['label'];
        $row['expected_result_label'] = $expectedShouldBeCorrect ? 'Correct Audio' : 'Wrong Audio';
        $row['reason'] = $this->reasonFrom($classification, $expectedShouldBeCorrect, $finalCorrectness, $recordingAccepted, $result);

        return $row;
    }

    private function reasonFrom(
        array $classification,
        bool $expectedShouldBeCorrect,
        bool $finalCorrectness,
        bool $recordingAccepted,
        array $result = []
    ): ?string {
        $code = (string) ($classification['code'] ?? '');

        if (! $recordingAccepted || $code === 'NA' || str_starts_with($code, 'invalid_audio') || $code === 'wrong_audio_rejected') {
            return $result['learner_retry_message']
                ?? $result['failure_reason']
                ?? 'Recording was invalid or could not be scored as usable audio.';
        }

        return match ($code) {
            'TP' => 'Expected correct audio, and ASR scored it correct.',
            'TN' => 'Expected wrong audio, and ASR scored it incorrect.',
            'FP' => 'Expected wrong audio, but ASR scored it correct.',
            'FN' => 'Expected correct audio, but ASR scored it incorrect.',
            default => $expectedShouldBeCorrect === $finalCorrectness
                ? 'Expected test label matched the ASR correctness result.'
                : 'Expected test label did not match the ASR correctness result.',
        };
    }

    private function labelFor(string $code): string
    {
        return match ($code) {
            'TP' => 'True Positive',
            'TN' => 'True Negative',
            'FP' => 'False Positive',
            'FN' => 'False Negative',
            'invalid_audio_rejected', 'invalid_audio_accepted', 'wrong_audio_rejected' => 'Not Applicable',
            default => $code !== '' ? $code : 'Not Applicable',
        };
    }

    private function codeFor(string $label): string
    {
        return match ($label) {
            'True Positive', 'true_positive' => 'TP',
            'True Negative', 'true_negative' => 'TN',
            'False Positive', 'false_positive' => 'FP',
            'False Negative', 'false_negative' => 'FN',
            'Not Applicable', 'not_applicable' => 'NA',
            default => $label,
        };
    }
}
