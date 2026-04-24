<?php

namespace App\Services;

class AnswerMatchingService
{
    public function normalizeAnswer(string $answer): string
    {
        $normalized = strtolower(trim($answer));
        $normalized = preg_replace('/[[:punct:]]+/', '', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    public function isAcceptedAnswer(string $answer, string|array|null $acceptedAnswers): bool
    {
        $normalizedAnswer = $this->normalizeAnswer($answer);

        if ($normalizedAnswer === '') {
            return false;
        }

        $answers = is_array($acceptedAnswers)
            ? $acceptedAnswers
            : explode('|', (string) $acceptedAnswers);

        foreach ($answers as $acceptedAnswer) {
            if ($this->normalizeAnswer((string) $acceptedAnswer) === $normalizedAnswer) {
                return true;
            }
        }

        return false;
    }
}
