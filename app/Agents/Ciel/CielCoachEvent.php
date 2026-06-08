<?php

namespace App\Agents\Ciel;

final readonly class CielCoachEvent
{
    public function __construct(
        public string $sourceType,
        public string $context,
        public ?string $activityType = null,
        public ?string $targetText = null,
        public ?string $targetSound = null,
        public ?string $instructionMode = null,
        public ?int $learnerId = null,
        public ?int $attemptNumber = null,
        public ?int $remainingAttempts = null,
        public ?bool $isCorrect = null,
        public ?string $transcript = null,
        public ?string $errorType = null,
        public ?string $similarityLabel = null,
        public ?float $asrConfidence = null,
        public ?bool $uncertain = null,
        public ?bool $retryRequired = null,
        public ?int $correctStreak = null,
        public ?int $incorrectStreak = null,
        public ?string $weakSkill = null,
        public ?string $skillSignal = null,
        public ?bool $sectionCompleted = null,
        public ?bool $finalCompletion = null,
        public bool $congratsAllowed = false,
    ) {}

    public static function fromArray(array $event): self
    {
        $context = self::key($event['context'] ?? 'module_practice');
        $sourceType = self::key($event['source_type'] ?? (
            $context === 'listening_game_practice' ? 'readirect_game' : 'module'
        ));

        return new self(
            sourceType: $sourceType,
            context: $context,
            activityType: self::nullableKey($event['activity_type'] ?? null),
            targetText: self::nullableText($event['target_text'] ?? null),
            targetSound: self::nullableText($event['target_sound'] ?? null),
            instructionMode: self::nullableKey($event['instruction_mode'] ?? $event['status'] ?? null),
            learnerId: self::nullableInt($event['learner_id'] ?? null),
            attemptNumber: self::nullableInt($event['attempt_number'] ?? null),
            remainingAttempts: self::nullableInt($event['remaining_attempts'] ?? null),
            isCorrect: self::nullableBool($event['is_correct'] ?? null),
            transcript: self::nullableText($event['transcript'] ?? null),
            errorType: self::nullableKey($event['error_type'] ?? null),
            similarityLabel: self::nullableKey($event['similarity_label'] ?? null),
            asrConfidence: self::nullableFloat($event['asr_confidence'] ?? null),
            uncertain: self::nullableBool($event['uncertain'] ?? null),
            retryRequired: self::nullableBool($event['retry_required'] ?? null),
            correctStreak: self::nullableInt($event['correct_streak'] ?? null),
            incorrectStreak: self::nullableInt($event['incorrect_streak'] ?? null),
            weakSkill: self::nullableKey($event['weak_skill'] ?? null),
            skillSignal: self::nullableKey($event['skill_signal'] ?? null),
            sectionCompleted: self::nullableBool($event['section_completed'] ?? null),
            finalCompletion: self::nullableBool($event['final_completion'] ?? null),
            congratsAllowed: self::nullableBool($event['congrats_allowed'] ?? false) === true,
        );
    }

    public function isAssessmentContext(): bool
    {
        return in_array($this->context, [
            'assessment',
            'diagnostic_assessment',
            'final_assessment',
        ], true);
    }

    public function isListeningGame(): bool
    {
        return $this->context === 'listening_game_practice'
            && $this->sourceType === 'readirect_game';
    }

    private static function key(mixed $value): string
    {
        return str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->toString();
    }

    private static function nullableKey(mixed $value): ?string
    {
        $key = self::key($value);

        return $key === '' ? null : $key;
    }

    private static function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));

        return $text === '' ? null : $text;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
