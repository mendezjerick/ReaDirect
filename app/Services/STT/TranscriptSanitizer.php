<?php

namespace App\Services\STT;

class TranscriptSanitizer
{
    private const HESITATIONS = [
        '/\b(um+|uh+|er+|ah+|hmm+)\b/i',
    ];

    public function sanitize(?string $transcript): string
    {
        $value = trim((string) $transcript);

        foreach (self::HESITATIONS as $pattern) {
            $value = preg_replace($pattern, ' ', $value) ?? $value;
        }

        $value = preg_replace('/[^\pL\pN\s\'-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function safeError(?string $error): ?string
    {
        $value = trim((string) $error);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[A-Z]:\\\\[^\s]+/i', '[path]', $value) ?? $value;
        $value = preg_replace('#/[^\s]+#', '[path]', $value) ?? $value;

        return mb_substr($value, 0, 240);
    }
}
