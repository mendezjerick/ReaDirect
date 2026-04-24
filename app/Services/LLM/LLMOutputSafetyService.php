<?php

namespace App\Services\LLM;

class LLMOutputSafetyService
{
    private const UNSAFE_PHRASES = [
        'wrong',
        'failed',
        'bad',
        'poor',
        'stupid',
        'dumb',
        'cannot read',
        'disorder',
        'disability',
        'diagnosis',
    ];

    public function sanitize(?string $output, string $fallback): array
    {
        $clean = $this->clean((string) $output);

        if ($clean === '') {
            return $this->fallback($fallback, 'empty_output');
        }

        if (mb_strlen($clean) > 300) {
            return $this->fallback($fallback, 'too_long');
        }

        foreach (self::UNSAFE_PHRASES as $phrase) {
            if (str_contains(mb_strtolower($clean), $phrase)) {
                return $this->fallback($fallback, 'unsafe_language');
            }
        }

        return [
            'text' => $this->limitSentences($clean),
            'fallback_used' => false,
            'safety_status' => 'safe',
        ];
    }

    private function clean(string $output): string
    {
        $clean = strip_tags($output);
        $clean = preg_replace('/[*_`#>\[\]]+/', '', $clean) ?? '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';

        return trim($clean, " \t\n\r\0\x0B\"'");
    }

    private function limitSentences(string $text): string
    {
        preg_match_all('/[^.!?]+[.!?]*/', $text, $matches);
        $sentences = array_values(array_filter(array_map('trim', $matches[0] ?? [])));

        if (count($sentences) <= 2) {
            return $text;
        }

        return trim(implode(' ', array_slice($sentences, 0, 2)));
    }

    private function fallback(string $fallback, string $status): array
    {
        return [
            'text' => $this->clean($fallback),
            'fallback_used' => true,
            'safety_status' => $status,
        ];
    }
}
