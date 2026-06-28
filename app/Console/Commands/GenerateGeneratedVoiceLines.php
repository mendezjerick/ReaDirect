<?php

namespace App\Console\Commands;

use App\Models\GeneratedVoiceLine;
use App\Services\VoiceLines\VoiceLineService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenerateGeneratedVoiceLines extends Command
{
    protected $signature = 'readirect:voice-lines:generate
        {--line-key= : Generate one line key}
        {--limit= : Maximum rows to generate}
        {--chunk=4 : Number of rows per TTS batch request}
        {--force : Regenerate files even when DB paths already exist}';

    protected $description = 'Generate Stage 1 reference-style and Stage 2 Kokoro-identity voice line audio.';

    public function handle(VoiceLineService $voiceLines): int
    {
        $query = GeneratedVoiceLine::query()
            ->where('is_dynamic_template', false)
            ->whereNotNull('text')
            ->orderBy('id');

        if ($this->option('line-key')) {
            $query->where('line_key', (string) $this->option('line-key'));
        }

        if (! $this->option('force')) {
            $query->where(function ($nested): void {
                $nested->whereNull('reference_style_audio_path')
                    ->orWhereNull('kokoro_identity_audio_path')
                    ->orWhere('status', '!=', 'generated');
            });
        }

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('No generated voice line rows need generation.');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) config('readirect.tts.base_url', 'http://127.0.0.1:8002'), '/');
        $endpoint = $baseUrl.'/voice-lines/generate-batch';
        $chunkSize = max(1, (int) $this->option('chunk'));
        $publicRoot = (string) config('readirect.voice_database.public_disk_root', 'tts/generated_voice_lines');
        $outputRoot = storage_path('app/public/'.$publicRoot);
        $generated = 0;
        $failed = 0;

        foreach ($rows->chunk($chunkSize) as $chunk) {
            $items = $chunk->map(fn (GeneratedVoiceLine $line): array => [
                'id' => $line->id,
                'line_key' => $line->line_key,
                'agent' => $line->agent,
                'intent' => $line->intent,
                'text' => $line->text,
                'voice_id' => $line->voice_id,
                'is_static' => $line->is_static,
                'is_defense_demo' => $line->is_defense_demo,
            ])->values()->all();

            try {
                $response = Http::timeout(900)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, [
                        'items' => $items,
                        'mode' => 'pregenerate_two_stage',
                        'engine' => 'index_tts2',
                        'fallback' => true,
                        'force' => (bool) $this->option('force'),
                        'active_stage' => config('readirect.voice_database.active_stage', 'reference_style'),
                        'output_root' => $outputRoot,
                        'public_relative_root' => $publicRoot,
                    ]);
            } catch (ConnectionException $exception) {
                $this->error('Could not reach ReaDirect-TTS batch endpoint: '.$exception->getMessage());

                return self::FAILURE;
            }

            if (! $response->successful()) {
                $this->error("TTS batch endpoint failed with HTTP {$response->status()}: ".Str::limit($response->body(), 500));

                return self::FAILURE;
            }

            foreach (($response->json('items') ?? []) as $item) {
                $line = GeneratedVoiceLine::query()->where('line_key', $item['line_key'] ?? '')->first();
                if (! $line) {
                    continue;
                }

                $stage1 = $item['stage1'] ?? [];
                $stage2 = $item['stage2'] ?? [];
                $reference = $item['reference'] ?? [];

                $line->fill([
                    'selected_original_reference_audio_path' => $reference['path'] ?? null,
                    'selected_original_reference_duration_seconds' => $reference['duration_seconds'] ?? null,
                    'selected_original_reference_priority' => $reference['priority'] ?? null,
                    'selected_original_reference_weight' => $reference['weight'] ?? null,
                    'reference_style_audio_path' => $stage1['public_audio_path'] ?? null,
                    'reference_style_duration_seconds' => $stage1['duration_seconds'] ?? null,
                    'reference_style_engine' => $stage1['engine_used'] ?? null,
                    'reference_style_status' => $stage1['status'] ?? 'failed',
                    'reference_style_error' => $stage1['error'] ?? null,
                    'kokoro_identity_audio_path' => $stage2['public_audio_path'] ?? null,
                    'kokoro_identity_duration_seconds' => $stage2['duration_seconds'] ?? null,
                    'kokoro_identity_engine' => $stage2['engine_used'] ?? null,
                    'kokoro_identity_voice_id' => $stage2['kokoro_voice_id'] ?? $voiceLines->voiceIdForAgent($line->agent),
                    'kokoro_identity_style_source_path' => $stage2['style_source_path'] ?? null,
                    'kokoro_identity_status' => $stage2['status'] ?? 'failed',
                    'kokoro_identity_error' => $stage2['error'] ?? null,
                    'defense_audio_path' => $item['defense_audio_path'] ?? ($stage1['public_audio_path'] ?? null),
                    'stage2_demo_audio_path' => $item['stage2_demo_audio_path'] ?? ($stage2['public_audio_path'] ?? null),
                    'active_audio_path' => $item['active_audio_path'] ?? null,
                    'active_audio_type' => $item['active_audio_type'] ?? config('readirect.voice_database.active_stage', 'reference_style'),
                    'speaker_reference_path' => $stage2['speaker_reference_path'] ?? null,
                    'emotion_prompt' => $item['emotion_prompt'] ?? null,
                    'sample_rate' => $item['sample_rate'] ?? 24000,
                    'channels' => $item['channels'] ?? 1,
                    'format' => $item['format'] ?? 'wav',
                    'status' => $item['status'] ?? 'failed',
                    'generation_error' => $item['generation_error'] ?? null,
                    'cache_key' => $item['cache_key'] ?? null,
                    'checksum' => $item['checksum'] ?? null,
                ]);
                $line->save();

                if ($line->status === 'generated' || $line->status === 'fallback_generated') {
                    $generated++;
                } else {
                    $failed++;
                }

                $this->line("{$line->line_key}: {$line->status}");
            }
        }

        $this->info("Generated {$generated} voice line row(s); {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
