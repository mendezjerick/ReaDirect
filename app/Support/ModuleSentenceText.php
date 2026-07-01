<?php

namespace App\Support;

class ModuleSentenceText
{
    public static function display(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        if ($text === '') {
            return '';
        }

        $text = preg_replace_callback(
            '/(^|[.!?]\s+)([a-z])/',
            fn (array $matches): string => $matches[1].strtoupper($matches[2]),
            $text,
        ) ?? $text;

        return preg_match('/[.!?]\z/', $text) ? $text : $text.'.';
    }

    public static function scoringTarget(string $text, ?string $moduleKey, ?string $activityType): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        if ($moduleKey === 'module_3' && $activityType === 'simple_sentence_reading') {
            return rtrim($text, ".!? \t\n\r\0\x0B");
        }

        return $text;
    }
}
