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
            $normalizedAccepted = $this->normalizeAnswer((string) $acceptedAnswer);

            if ($normalizedAccepted === $normalizedAnswer) {
                return true;
            }

            if ($this->isSpokenLetterAlias($normalizedAnswer, $normalizedAccepted)) {
                return true;
            }
        }

        return false;
    }

    private function isSpokenLetterAlias(string $answer, string $acceptedAnswer): bool
    {
        if (mb_strlen($answer) !== 1 || $acceptedAnswer === '') {
            return false;
        }

        $aliases = [
            'a' => ['a', 'aye', 'ay'],
            'b' => ['be', 'bee'],
            'c' => ['see', 'sea'],
            'i' => ['i', 'eye'],
            'o' => ['o', 'oh'],
            'q' => ['cue', 'queue'],
            'r' => ['are'],
            'u' => ['you', 'yew'],
            'x' => ['ex'],
            'y' => ['why'],
        ];

        return in_array($acceptedAnswer, $aliases[$answer] ?? [], true);
    }
}
