<?php

namespace App\Services\ASR;

use App\Models\AsrSupervisedReinforcementCase;
use App\Models\User;
use App\Services\AI\ReadirectAIService;
use Illuminate\Validation\ValidationException;

class SupervisedReinforcementService
{
    public function __construct(private readonly ReadirectAIService $ai)
    {
    }

    public function approveFalseRejection(array $sandboxResult, ?User $user, ?array $wordCase = null): AsrSupervisedReinforcementCase
    {
        $isWordCase = is_array($wordCase);
        $expected = $isWordCase
            ? trim((string) ($wordCase['expected_text'] ?? $wordCase['expected_word'] ?? ''))
            : trim((string) ($sandboxResult['expected_text'] ?? data_get($sandboxResult, 'request_context.expected_text', '')));
        $raw = $isWordCase
            ? trim((string) ($wordCase['raw_transcript'] ?? $wordCase['recognized_word'] ?? ''))
            : trim((string) ($sandboxResult['raw_transcript'] ?? data_get($sandboxResult, 'ai_response.raw_transcript', '')));
        $promptType = $isWordCase
            ? 'word'
            : trim((string) ($sandboxResult['prompt_type'] ?? data_get($sandboxResult, 'ai_response.prompt_type', '')));
        $accepted = $isWordCase
            ? (bool) ($wordCase['counts_as_correct'] ?? false)
            : (bool) data_get($sandboxResult, 'scoring.accepted', $sandboxResult['accepted'] ?? false);
        $retryRequired = (bool) ($sandboxResult['retry_required'] ?? false);
        $uncertain = (bool) ($sandboxResult['uncertain'] ?? false);

        $this->assertApprovable($expected, $raw, $promptType, $accepted, $retryRequired, $uncertain, ! $isWordCase);

        $case = AsrSupervisedReinforcementCase::query()->updateOrCreate(
            ['case_hash' => $this->caseHash($expected, $raw, $promptType)],
            [
                'status' => 'approved',
                'expected_text' => $expected,
                'raw_transcript' => $raw,
                'normalized_transcript' => $sandboxResult['normalized_transcript'] ?? null,
                'corrected_transcript' => $sandboxResult['corrected_transcript'] ?? null,
                'displayed_transcript' => $sandboxResult['displayed_transcript'] ?? null,
                'prompt_type' => $promptType,
                'task_type' => $sandboxResult['task_type'] ?? data_get($sandboxResult, 'request_context.task_type'),
                'activity_type' => $sandboxResult['activity_type'] ?? data_get($sandboxResult, 'request_context.activity_type'),
                'assessment_type' => $sandboxResult['assessment_type'] ?? data_get($sandboxResult, 'request_context.assessment_type'),
                'module_type' => data_get($sandboxResult, 'request_context.module_type') ?? data_get($sandboxResult, 'request_context.module_key'),
                'item_id' => $this->itemId($sandboxResult, $wordCase),
                'item_source' => data_get($sandboxResult, 'request_context.content_metadata.item_source'),
                'similarity_scores' => $this->similarityScores($sandboxResult, $wordCase),
                'decision_result' => $this->decisionResult($sandboxResult, $wordCase),
                'request_context' => $sandboxResult['request_context'] ?? null,
                'ai_response' => $sandboxResult['ai_response'] ?? null,
                'confirmed_by' => $user?->id,
                'confirmed_at' => now(),
            ]
        );

        $reinforcementResponse = $this->ai->reinforcementCorrection([
            'expected_text' => $expected,
            'raw_transcript' => $raw,
            'prompt_type' => $promptType,
            'accepted' => false,
            'retry_required' => false,
            'uncertain' => false,
            'correction_strategy_used' => (string) ($sandboxResult['correction_strategy_used'] ?? 'none'),
            'created_by' => $user?->email ?: (string) ($user?->id ?? 'admin'),
            'source' => 'true_sandbox_supervised',
            'notes' => $isWordCase
                ? 'Manually approved sentence word-level false rejection from True Sandbox.'
                : 'Manually approved false rejection from True Sandbox.',
            'supervised_reinforcement_enabled' => true,
            'developer_reinforcement_enabled' => true,
            'developer_user_role' => 'admin',
        ]);

        $case->forceFill([
            'reinforcement_response' => $reinforcementResponse,
        ])->save();

        return $case->refresh();
    }

    private function assertApprovable(string $expected, string $raw, string $promptType, bool $accepted, bool $retryRequired, bool $uncertain, bool $requireRejectedDecision): void
    {
        $errors = [];

        if ($expected === '') {
            $errors['expected_text'] = 'Expected text is required before adding supervised reinforcement.';
        }

        if ($raw === '') {
            $errors['raw_transcript'] = 'Raw transcript is required before adding supervised reinforcement.';
        }

        if (! in_array($this->normalizePromptType($promptType), ['letter', 'word', 'rhyme', 'rhyming_word', 'sentence', 'paragraph', 'passage', 'reading_passage'], true)) {
            $errors['prompt_type'] = 'This prompt type is not supported by supervised reinforcement.';
        }

        if ($accepted && $requireRejectedDecision) {
            $errors['decision'] = 'Only rejected ASR decisions can be added as supervised reinforcement.';
        } elseif ($accepted) {
            $errors['decision'] = 'Only incorrect aligned words can be added as supervised reinforcement.';
        }

        if ($retryRequired || $uncertain) {
            $errors['quality'] = 'Retry-required or uncertain audio cannot be added to supervised reinforcement.';
        }

        if ($this->normalizeText($expected) !== '' && $this->normalizeText($expected) === $this->normalizeText($raw)) {
            $errors['raw_transcript'] = 'The raw transcript already matches the expected text.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function similarityScores(array $sandboxResult, ?array $wordCase = null): array
    {
        return array_filter([
            'phonetic_similarity_score' => $sandboxResult['phonetic_similarity_score'] ?? null,
            'composite_score' => $sandboxResult['composite_score'] ?? null,
            'gop_score' => $sandboxResult['gop_score'] ?? null,
            'dynamic_correction_confidence' => $sandboxResult['dynamic_correction_confidence'] ?? null,
            'dynamic_spelling_similarity' => $sandboxResult['dynamic_spelling_similarity'] ?? null,
            'dynamic_phoneme_similarity' => $sandboxResult['dynamic_phoneme_similarity'] ?? null,
            'asr_spelling_variant_confidence' => $sandboxResult['asr_spelling_variant_confidence'] ?? null,
            'raw_wer' => $sandboxResult['raw_wer'] ?? null,
            'corrected_wer' => $sandboxResult['corrected_wer'] ?? null,
            'raw_cer' => $sandboxResult['raw_cer'] ?? null,
            'corrected_cer' => $sandboxResult['corrected_cer'] ?? null,
            'word_alignment_confidence' => $wordCase['alignment_confidence'] ?? $wordCase['dynamic_correction_confidence'] ?? null,
            'word_spelling_similarity' => $wordCase['spelling_similarity'] ?? null,
            'word_phoneme_similarity' => $wordCase['phoneme_similarity'] ?? null,
            'word_gop_score' => $wordCase['gop_score'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function decisionResult(array $sandboxResult, ?array $wordCase = null): array
    {
        return [
            'accepted' => (bool) data_get($sandboxResult, 'scoring.accepted', $sandboxResult['accepted'] ?? false),
            'retry_required' => (bool) ($sandboxResult['retry_required'] ?? false),
            'uncertain' => (bool) ($sandboxResult['uncertain'] ?? false),
            'correction_strategy_used' => $sandboxResult['correction_strategy_used'] ?? null,
            'correction_reason' => data_get($sandboxResult, 'scoring.correction_reason') ?? ($sandboxResult['dynamic_correction_reason'] ?? $sandboxResult['variant_reason'] ?? null),
            'word_case' => $wordCase,
        ];
    }

    private function itemId(array $sandboxResult, ?array $wordCase): ?string
    {
        $itemId = data_get($sandboxResult, 'request_context.item_id');

        if (! is_array($wordCase)) {
            return $itemId;
        }

        $index = $wordCase['index'] ?? $wordCase['word_index'] ?? null;

        return $itemId ? $itemId.':word:'.$index : null;
    }

    private function caseHash(string $expected, string $raw, string $promptType): string
    {
        return hash('sha256', implode('|', [
            $this->normalizePromptType($promptType),
            $this->normalizeText($expected),
            $this->normalizeText($raw),
        ]));
    }

    private function normalizePromptType(string $value): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim($value)));
    }

    private function normalizeText(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^\pL\pN\s]+/u', '', $normalized) ?? '';

        return preg_replace('/\s+/u', ' ', $normalized) ?? '';
    }
}
