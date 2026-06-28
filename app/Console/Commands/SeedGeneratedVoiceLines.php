<?php

namespace App\Console\Commands;

use App\Models\GeneratedVoiceLine;
use App\Services\VoiceLines\VoiceLineCatalog;
use App\Services\VoiceLines\VoiceLineService;
use Illuminate\Console\Command;

class SeedGeneratedVoiceLines extends Command
{
    protected $signature = 'readirect:voice-lines:seed {--fresh : Delete existing generated voice line registry rows before seeding}';

    protected $description = 'Seed the generated ReaDirect voice line registry.';

    public function handle(VoiceLineCatalog $catalog, VoiceLineService $voiceLines): int
    {
        if ($this->option('fresh')) {
            GeneratedVoiceLine::query()->delete();
        }

        $count = 0;
        foreach ($catalog->allSeedLines() as $line) {
            $text = (string) ($line['text'] ?? '');
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
                    ])),
                    'text' => $text,
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

        $this->info("Seeded {$count} generated voice line registry row(s).");

        return self::SUCCESS;
    }
}
