<?php

namespace App\Console\Commands;

use App\Models\GeneratedVoiceLine;
use App\Services\VoiceLines\VoiceLineCatalog;
use App\Services\VoiceLines\VoiceLineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedGeneratedVoiceLines extends Command
{
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

            GeneratedVoiceLine::query()->updateOrCreate(
                ['line_key' => $line['line_key']],
                [
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
                ],
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
}
