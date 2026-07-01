<?php

namespace App\Console\Commands;

use App\Models\GeneratedVoiceLine;
use App\Services\VoiceLines\VoiceLineCatalog;
use App\Services\VoiceLines\VoiceLineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedGeneratedVoiceLines extends Command
{
    private ?array $module1LetterPromptIds = null;

    protected $signature = 'readirect:voice-lines:seed
        {--fresh : Delete existing generated voice line registry rows before seeding}
        {--reset-ciel-echoes : Delete generated Ciel echo audio and reset current echo rows to pending}
        {--prune-obsolete-ciel-echoes : Delete old hardcoded Ciel echo fixture rows that are no longer in the catalog}';

    protected $description = 'Seed the generated ReaDirect voice line registry.';

    public function handle(VoiceLineCatalog $catalog, VoiceLineService $voiceLines): int
    {
        if ($this->option('fresh')) {
            GeneratedVoiceLine::query()->delete();
        }

        if ($this->option('reset-ciel-echoes')) {
            $echoRows = $this->currentCielEchoRows()->get();
            $deletedFiles = $this->deleteGeneratedVoiceLineAudio($echoRows);
            $deletedFiles += $this->deleteOrphanCielEchoAudio();
            $resetRows = $echoRows->count();

            $this->currentCielEchoRows()->update($this->cielEchoResetValues());

            $this->info("Reset {$resetRows} current Ciel echo row(s) and deleted {$deletedFiles} generated audio file(s).");
        }

        $lines = $catalog->allSeedLines();
        $lineKeys = array_column($lines, 'line_key');

        if ($this->option('prune-obsolete-ciel-echoes')) {
            $obsoleteEchoRows = GeneratedVoiceLine::query()
                ->where('agent', 'ciel')
                ->where(function ($query) use ($lineKeys): void {
                    $query
                        ->where('line_key', 'like', 'target_word_echo.%')
                        ->orWhere('line_key', 'like', 'try_again_with_target.%')
                        ->orWhere('line_key', 'like', 'correct_word_support.%')
                        ->orWhere(function ($nested) use ($lineKeys): void {
                            $nested
                                ->whereIn('intent', ['module_echo', 'module_echo_initial', 'module_echo_correct'])
                                ->whereNotIn('line_key', $lineKeys);
                        })
                        ->orWhere(function ($nested) use ($lineKeys): void {
                            $nested
                                ->where('line_key', 'like', 'ciel.module_echo.%')
                                ->whereNotIn('line_key', $lineKeys);
                        });
                })
                ->get();

            $deletedFiles = $this->deleteVoiceLineAudio($obsoleteEchoRows);
            $deletedRows = $obsoleteEchoRows->count();

            GeneratedVoiceLine::query()
                ->whereIn('id', $obsoleteEchoRows->pluck('id')->all())
                ->delete();

            $this->info("Pruned {$deletedRows} obsolete Ciel echo row(s) and {$deletedFiles} audio file(s).");
        }

        $count = 0;
        foreach ($lines as $line) {
            $text = (string) ($line['text'] ?? '');
            $synthesisText = trim((string) ($line['synthesis_text'] ?? '')) ?: null;
            $status = ($line['is_dynamic_template'] ?? false) ? 'skipped_dynamic' : 'pending';
            $existing = GeneratedVoiceLine::query()
                ->where('line_key', $line['line_key'])
                ->first();
            $existingReferenceAudio = $this->existingReferenceAudioValues($line, $existing);

            GeneratedVoiceLine::query()->updateOrCreate(
                ['line_key' => $line['line_key']],
                array_merge([
                    'agent' => $line['agent'],
                    'intent' => $line['intent'],
                    'context' => $line['context'],
                    'source_repo' => $line['source_repo'],
                    'source_file' => $line['source_file'],
                    'source_hash' => hash('sha256', implode('|', [
                        $line['source_repo'],
                        $line['source_file'],
                        $line['line_key'],
                        $text,
                        $synthesisText ?? '',
                    ])),
                    'text' => $text,
                    'synthesis_text' => $synthesisText,
                    'text_hash' => $voiceLines->textHash($text),
                    'voice_id' => $voiceLines->voiceIdForAgent($line['agent']),
                    'engine' => 'index_tts2',
                    'expressive_engine' => 'index_tts2',
                    'kokoro_identity_voice_id' => $voiceLines->voiceIdForAgent($line['agent']),
                    'reference_style_status' => $status,
                    'kokoro_identity_status' => $status,
                    'active_audio_type' => config('readirect.voice_database.active_stage', 'reference_style'),
                    'sample_rate' => 24000,
                    'channels' => 1,
                    'format' => 'wav',
                    'status' => $status,
                    'is_static' => (bool) $line['is_static'],
                    'is_dynamic_template' => (bool) $line['is_dynamic_template'],
                    'is_defense_demo' => (bool) $line['is_defense_demo'],
                ], $existingReferenceAudio),
            );
            $count++;
        }

        if ($this->option('reset-ciel-echoes')) {
            $this->currentCielEchoRows()->update($this->cielEchoResetValues());
        }

        $this->info("Seeded {$count} generated voice line registry row(s).");

        return self::SUCCESS;
    }

    private function deleteVoiceLineAudio(iterable $rows): int
    {
        $paths = [];

        foreach ($rows as $row) {
            foreach ([
                'selected_original_reference_audio_path',
                'reference_style_audio_path',
                'kokoro_identity_audio_path',
                'defense_audio_path',
                'stage2_demo_audio_path',
                'active_audio_path',
            ] as $column) {
                $path = trim((string) ($row->{$column} ?? ''));
                if ($path !== '') {
                    $paths[$path] = true;
                }
            }
        }

        $deleted = 0;
        foreach (array_keys($paths) as $path) {
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            Storage::disk('public')->delete($path);
            $deleted++;
        }

        return $deleted;
    }

    private function existingReferenceAudioValues(array $line, ?GeneratedVoiceLine $existing): array
    {
        if ((bool) ($line['is_dynamic_template'] ?? false)) {
            return [];
        }

        $path = $this->existingPublicPath($existing?->reference_style_audio_path)
            ?? $this->findExistingReferenceAudioPath($line);

        if (! $path) {
            return [];
        }

        $activeStage = (string) config('readirect.voice_database.active_stage', 'reference_style');

        return [
            'reference_style_audio_path' => $path,
            'reference_style_engine' => $existing?->reference_style_engine ?: 'index_tts2',
            'reference_style_status' => 'generated',
            'reference_style_error' => null,
            'defense_audio_path' => $path,
            'active_audio_path' => $activeStage === VoiceLineService::REFERENCE_STYLE
                ? $path
                : $existing?->active_audio_path,
            'active_audio_type' => $activeStage,
            'status' => 'generated',
            'generation_error' => null,
            'checksum' => $this->checksumForPublicPath($path) ?? $existing?->checksum,
        ];
    }

    private function existingPublicPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    private function findExistingReferenceAudioPath(array $line): ?string
    {
        $agent = trim((string) ($line['agent'] ?? ''));
        $lineKey = trim((string) ($line['line_key'] ?? ''));
        if ($agent === '' || $lineKey === '') {
            return null;
        }

        foreach ($this->candidateAudioFilenameStems($line) as $stem) {
            $path = $this->findReferenceAudioByStem($agent, $stem);
            if ($path) {
                return $path;
            }
        }

        return null;
    }

    private function candidateAudioFilenameStems(array $line): array
    {
        $lineKey = trim((string) ($line['line_key'] ?? ''));
        $stems = [$this->audioFilenameStem($lineKey)];

        if (preg_match('/\Aciel\.module_echo\.correct\.module_1\.letter\.([a-z])\z/', $lineKey, $matches)) {
            $promptId = $this->module1LetterPromptIds()[$matches[1]] ?? null;
            if ($promptId) {
                $stems[] = $this->audioFilenameStem('ciel.module_echo.correct.module_1.'.$promptId);
            }
        }

        return array_values(array_unique(array_filter($stems)));
    }

    private function findReferenceAudioByStem(string $agent, string $stem): ?string
    {
        $root = trim((string) config('readirect.voice_database.public_disk_root', 'tts/generated_voice_lines'), '/');
        $folder = "{$root}/reference_style/{$agent}";
        if (! Storage::disk('public')->exists($folder)) {
            return null;
        }

        $matches = [];
        foreach (Storage::disk('public')->allFiles($folder) as $path) {
            $name = strtolower(pathinfo($path, PATHINFO_FILENAME));
            if ($name === $stem || str_starts_with($name, $stem.'_')) {
                $matches[] = $path;
            }
        }

        sort($matches, SORT_NATURAL);

        return $matches[0] ?? null;
    }

    private function audioFilenameStem(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($value)), '_');
    }

    private function module1LetterPromptIds(): array
    {
        if ($this->module1LetterPromptIds !== null) {
            return $this->module1LetterPromptIds;
        }

        $path = database_path('seed-data/readirect/module1_letter_sound_activities_adaptive_v2.csv');
        if (! is_file($path)) {
            return $this->module1LetterPromptIds = [];
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '\\') ?: [];
        $map = [];

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $row = array_combine($headers, $data);
            if (! is_array($row)) {
                continue;
            }

            if (($row['module_key'] ?? '') !== 'module_1' || ($row['activity_type'] ?? '') !== 'letter_pair_identification') {
                continue;
            }

            if (! $this->active($row['is_active'] ?? null)) {
                continue;
            }

            $letter = strtolower(trim((string) ($row['expected_text'] ?? '')));
            $promptId = strtolower(trim((string) ($row['prompt_id'] ?? '')));
            if (preg_match('/\A[a-z]\z/', $letter) && $promptId !== '' && ! isset($map[$letter])) {
                $map[$letter] = $promptId;
            }
        }

        fclose($handle);

        return $this->module1LetterPromptIds = $map;
    }

    private function checksumForPublicPath(string $path): ?string
    {
        $absolutePath = Storage::disk('public')->path($path);
        if (! is_file($absolutePath)) {
            return null;
        }

        return hash_file('sha256', $absolutePath) ?: null;
    }

    private function currentCielEchoRows()
    {
        return GeneratedVoiceLine::query()
            ->where('agent', 'ciel')
            ->where(function ($query): void {
                $query
                    ->whereIn('intent', ['module_echo', 'module_echo_initial', 'module_echo_correct'])
                    ->orWhere('line_key', 'like', 'ciel.module_echo.%')
                    ->orWhereIn('line_key', ['ciel.focus.echo_intro', 'ciel.focus.echo_repeat']);
            });
    }

    private function cielEchoResetValues(): array
    {
        return [
            'selected_original_reference_audio_path' => null,
            'selected_original_reference_duration_seconds' => null,
            'selected_original_reference_priority' => null,
            'selected_original_reference_weight' => null,
            'reference_style_audio_path' => null,
            'reference_style_duration_seconds' => null,
            'reference_style_engine' => null,
            'reference_style_status' => 'pending',
            'reference_style_error' => null,
            'kokoro_identity_audio_path' => null,
            'kokoro_identity_duration_seconds' => null,
            'kokoro_identity_engine' => null,
            'kokoro_identity_style_source_path' => null,
            'kokoro_identity_status' => 'pending',
            'kokoro_identity_error' => null,
            'defense_audio_path' => null,
            'stage2_demo_audio_path' => null,
            'active_audio_path' => null,
            'active_audio_type' => config('readirect.voice_database.active_stage', 'reference_style'),
            'speaker_reference_path' => null,
            'emotion_prompt' => null,
            'status' => 'pending',
            'generation_error' => null,
            'cache_key' => null,
            'checksum' => null,
            'updated_at' => now(),
        ];
    }

    private function deleteGeneratedVoiceLineAudio(iterable $rows): int
    {
        $paths = [];

        foreach ($rows as $row) {
            foreach ([
                'reference_style_audio_path',
                'kokoro_identity_audio_path',
                'defense_audio_path',
                'stage2_demo_audio_path',
                'active_audio_path',
            ] as $column) {
                $path = trim((string) ($row->{$column} ?? ''));
                if ($path !== '') {
                    $paths[$path] = true;
                }
            }
        }

        return $this->deletePublicPaths(array_keys($paths));
    }

    private function deleteOrphanCielEchoAudio(): int
    {
        $root = trim((string) config('readirect.voice_database.public_disk_root', 'tts/generated_voice_lines'), '/');
        $folders = [
            "{$root}/reference_style/ciel/friendly_encouragement",
            "{$root}/kokoro_identity/ciel/friendly_encouragement",
            "{$root}/reference_style/ciel/module_echo",
            "{$root}/kokoro_identity/ciel/module_echo",
        ];

        $paths = [];
        foreach ($folders as $folder) {
            if (! Storage::disk('public')->exists($folder)) {
                continue;
            }

            foreach (Storage::disk('public')->allFiles($folder) as $path) {
                $name = basename($path);
                $isEchoFile = str_starts_with($name, 'ciel_module_echo_')
                    || str_starts_with($name, 'ciel_focus_echo_')
                    || str_contains($path, '/module_echo/');

                if ($isEchoFile) {
                    $paths[$path] = true;
                }
            }
        }

        return $this->deletePublicPaths(array_keys($paths));
    }

    private function deletePublicPaths(array $paths): int
    {
        $deleted = 0;
        foreach ($paths as $path) {
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            Storage::disk('public')->delete($path);
            $deleted++;
        }

        return $deleted;
    }

    private function active(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
