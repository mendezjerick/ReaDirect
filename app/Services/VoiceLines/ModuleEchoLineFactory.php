<?php

namespace App\Services\VoiceLines;

use App\Models\ModuleAttemptItem;

class ModuleEchoLineFactory
{
    public const CORRECT_INTENT = 'module_echo_correct';
    public const FOCUS_SUPPORT_INTENT = 'focused_instruction';

    private const DURATION = [
        'target' => 3.0,
        'min' => 0.3,
        'max' => 8.0,
    ];

    private const MODULE_FILES = [
        'module1_letter_sound_activities_adaptive_v2.csv',
        'module2_word_reading_activities_adaptive_v2.csv',
        'module3_sentence_fluency_activities_adaptive_v2.csv',
    ];

    private const LETTER_ALIASES = [
        'A' => 'ay',
        'C' => 'see',
        'E' => 'ee',
        'F' => 'eff',
        'G' => 'jee',
        'H' => 'aitch',
        'I' => 'eye',
        'J' => 'jay',
        'K' => 'kay',
        'L' => 'el',
        'M' => 'em',
        'N' => 'en',
        'O' => 'oh',
        'Q' => 'cue',
        'R' => 'are',
        'S' => 'ess',
        'T' => 'tee',
        'U' => 'you',
        'V' => 'vee',
        'W' => 'double you',
        'X' => 'ex',
        'Y' => 'why',
    ];

    public function supportLines(): array
    {
        return [
            $this->line(
                'ciel.focus.echo_intro',
                self::FOCUS_SUPPORT_INTENT,
                "I'll say it first, listen carefully.",
                'Focus Mode echo intro',
                'app/Services/CielFocusModeService.php',
            ),
            $this->line(
                'ciel.focus.echo_repeat',
                self::FOCUS_SUPPORT_INTENT,
                "I'll repeat it one more time, listen closely.",
                'Focus Mode echo repeat intro',
                'app/Services/CielFocusModeService.php',
            ),
        ];
    }

    public function moduleLines(): array
    {
        $lines = [];
        $seenLineKeys = [];

        foreach (self::MODULE_FILES as $file) {
            foreach ($this->csv($file) as $row) {
                if (! $this->active($row['is_active'] ?? null)) {
                    continue;
                }

                $moduleKey = trim((string) ($row['module_key'] ?? ''));
                $promptId = $this->promptId($row);
                $target = $this->spokenTargetFromRow($row);

                if ($moduleKey === '' || $promptId === '' || $target === '') {
                    continue;
                }

                $sourceFile = (string) ($row['source_file'] ?? $file);
                [$displayText, $synthesisText] = $this->echoTexts($moduleKey, $target);
                $lineKey = $this->correctLineKey($moduleKey, $promptId, $target);

                if (isset($seenLineKeys[$lineKey])) {
                    continue;
                }
                $seenLineKeys[$lineKey] = true;

                $lines[] = $this->line(
                    $lineKey,
                    self::CORRECT_INTENT,
                    $displayText,
                    'Module item pronunciation echo',
                    'database/seed-data/readirect/'.$sourceFile,
                    $synthesisText,
                );
            }
        }

        return $lines;
    }

    public function forAttemptItem(ModuleAttemptItem $item): array
    {
        $snapshot = $item->prompt_snapshot ?? [];
        $payload = is_array($snapshot['payload'] ?? null) ? $snapshot['payload'] : [];
        $moduleKey = trim((string) ($payload['module_key'] ?? ''));
        $promptId = trim((string) ($item->source_csv_id ?? $payload['source_csv_id'] ?? ''));
        $target = $this->spokenTargetFromPayload($payload, $snapshot);

        if ($moduleKey === '' || $promptId === '' || $target === '') {
            return [];
        }

        [$displayText] = $this->echoTexts($moduleKey, $target);

        return [
            'target_type' => $this->targetType($moduleKey, (string) ($item->activity_type ?? '')),
            'target_text' => $target,
            'correct' => [
                'text' => $displayText,
                'line_key' => $this->correctLineKey($moduleKey, $promptId, $target),
                'intent' => self::CORRECT_INTENT,
            ],
        ];
    }

    public function correctLineKey(string $moduleKey, string $promptId, ?string $target = null): string
    {
        if ($moduleKey === 'module_1' && trim((string) $target) !== '') {
            return 'ciel.module_echo.correct.module_1.letter.'.$this->cleanKey(strtolower(trim((string) $target)));
        }

        if ($moduleKey === 'module_2' && trim((string) $target) !== '') {
            return 'ciel.module_echo.correct.module_2.word.'.$this->cleanKey(strtolower(trim((string) $target)));
        }

        if (in_array($moduleKey, ['module_3', 'advanced_module'], true) && trim((string) $target) !== '') {
            return 'ciel.module_echo.correct.module_3.sentence.'.substr(hash('sha1', strtolower(trim((string) $target))), 0, 12);
        }

        return 'ciel.module_echo.correct.'.$this->cleanKey($moduleKey).'.'.$this->cleanKey($promptId);
    }

    public function targetType(string $moduleKey, string $activityType = ''): string
    {
        $activity = strtolower($activityType);

        if ($moduleKey === 'module_1' || str_contains($activity, 'letter') || str_contains($activity, 'sound')) {
            return 'letter';
        }

        if ($moduleKey === 'module_3' || $moduleKey === 'advanced_module' || str_contains($activity, 'sentence') || str_contains($activity, 'paragraph')) {
            return 'sentence';
        }

        return 'word';
    }

    private function line(
        string $lineKey,
        string $intent,
        string $text,
        string $context,
        string $sourceFile,
        ?string $synthesisText = null,
    ): array
    {
        return [
            'line_key' => $lineKey,
            'agent' => 'ciel',
            'intent' => $intent,
            'context' => $context,
            'source_repo' => 'ReaDirect',
            'source_file' => $sourceFile,
            'text' => $text,
            'synthesis_text' => $synthesisText,
            'target_duration_seconds' => self::DURATION['target'],
            'min_duration_seconds' => self::DURATION['min'],
            'max_duration_seconds' => self::DURATION['max'],
            'protected' => true,
            'is_static' => true,
            'is_dynamic_template' => false,
            'is_defense_demo' => false,
            'fallback_only' => false,
            'generate_two_stage' => true,
        ];
    }

    private function echoTexts(string $moduleKey, string $target): array
    {
        if ($moduleKey === 'module_1') {
            $letter = strtoupper(substr($target, 0, 1));
            $synthesisTarget = self::LETTER_ALIASES[$letter] ?? $letter;

            return [
                "The letter is pronounced as {$letter}.",
                "The letter is pronounced as {$synthesisTarget}.",
            ];
        }

        if ($moduleKey === 'module_2') {
            return [
                "The word is pronounced as {$target}.",
                "The word is pronounced as {$target}.",
            ];
        }

        return [$target, null];
    }

    private function spokenTargetFromRow(array $row): string
    {
        $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $moduleKey = trim((string) ($row['module_key'] ?? $metadata['module_key'] ?? ''));
        $display = trim((string) ($row['prompt_text'] ?? $metadata['display_text'] ?? ''));
        $target = trim((string) (
            $row['expected_text']
            ?? $metadata['expected_answer']
            ?? $metadata['target_word']
            ?? $metadata['target_sentence']
            ?? $display
        ));

        return $this->normalizeSpokenTarget($moduleKey, $target, $display, $metadata);
    }

    private function spokenTargetFromPayload(array $payload, array $snapshot): string
    {
        $moduleKey = trim((string) ($payload['module_key'] ?? ''));
        $display = trim((string) (
            $payload['display_text']
            ?? $payload['target_sentence']
            ?? $payload['target_word']
            ?? $snapshot['prompt']
            ?? ''
        ));
        $target = trim((string) (
            $payload['expected_answer']
            ?? $payload['target_sentence']
            ?? $payload['target_word']
            ?? $snapshot['prompt']
            ?? $display
        ));

        return $this->normalizeSpokenTarget($moduleKey, $target, $display, $payload);
    }

    private function normalizeSpokenTarget(string $moduleKey, string $target, string $display, array $metadata): string
    {
        $target = trim(str_replace('|', ' ', $target));
        $display = trim($display);

        if ($this->targetType($moduleKey) === 'letter') {
            if (preg_match('/\A([A-Z])[a-z]\z/', $display, $matches)) {
                return $matches[1];
            }

            if (preg_match('/\A([A-Za-z])\z/', $target, $matches)) {
                return strtoupper($matches[1]);
            }

            $metadataDisplay = trim((string) ($metadata['display_grapheme'] ?? $metadata['display_text'] ?? ''));
            if (preg_match('/\A([A-Z])[a-z]\z/', $metadataDisplay, $matches)) {
                return $matches[1];
            }

            return strtoupper(substr($target, 0, 1));
        }

        if ($this->targetType($moduleKey) === 'word') {
            $word = $target !== '' ? $target : $display;

            return $word;
        }

        return $target !== '' ? $target : $display;
    }

    private function promptId(array $row): string
    {
        return trim((string) ($row['prompt_id'] ?? $row['id'] ?? ''));
    }

    private function cleanKey(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($value)) ?: 'unknown';
    }

    private function active(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private function csv(string $file): array
    {
        $path = database_path('seed-data/readirect/'.$file);
        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\');
        $rows = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($handle);

        return $rows;
    }
}
