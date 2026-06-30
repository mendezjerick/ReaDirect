<?php

namespace App\Support;

use App\Models\LearningContent;

class IsolatedLetterSet
{
    public const EXCLUDED = ['B', 'P', 'D', 'T'];

    public const ALLOWED = [
        'A', 'C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'Q', 'R', 'S', 'U', 'V', 'W', 'X', 'Y', 'Z',
    ];

    public static function allowed(): array
    {
        return self::ALLOWED;
    }

    public static function excluded(): array
    {
        return self::EXCLUDED;
    }

    public static function normalize(?string $value): ?string
    {
        $letter = strtoupper(trim((string) $value));

        return strlen($letter) === 1 && ctype_alpha($letter) ? $letter : null;
    }

    public static function isAllowed(?string $value): bool
    {
        $letter = self::normalize($value);

        return $letter !== null && in_array($letter, self::ALLOWED, true);
    }

    public static function isExcluded(?string $value): bool
    {
        $letter = self::normalize($value);

        return $letter !== null && in_array($letter, self::EXCLUDED, true);
    }

    public static function expectedLetterFromContent(LearningContent $content): ?string
    {
        return self::expectedLetter($content->payload ?? [], $content->prompt, $content->accepted_answers ?? []);
    }

    public static function expectedLetter(array $payload = [], ?string $prompt = null, array $acceptedAnswers = []): ?string
    {
        foreach (['expected_answer', 'target_letter', 'letter', 'target_word'] as $key) {
            $letter = self::normalize($payload[$key] ?? null);
            if ($letter !== null) {
                return $letter;
            }
        }

        foreach ($acceptedAnswers as $answer) {
            $letter = self::normalize(is_array($answer) ? ($answer['answer'] ?? null) : $answer);
            if ($letter !== null) {
                return $letter;
            }
        }

        return self::normalize($prompt);
    }

    public static function isIsolatedLetterActivity(?string $activityType, array $payload = [], ?string $contentType = null): bool
    {
        $value = strtolower((string) ($activityType ?? $payload['activity_type'] ?? $contentType ?? ''));
        if (str_contains($value, 'letter')) {
            return self::expectedLetter($payload) !== null;
        }

        return false;
    }
}
