<?php

namespace App\Console\Commands;

use App\Models\GeneratedVoiceLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ReportGeneratedVoiceLines extends Command
{
    protected $signature = 'readirect:voice-lines:report';

    protected $description = 'Write generated voice line database markdown and CSV reports.';

    public function handle(): int
    {
        $root = (string) config('readirect.voice_database.public_disk_root', 'tts/generated_voice_lines');
        $absoluteRoot = storage_path('app/public/'.$root);
        File::ensureDirectoryExists($absoluteRoot);

        $rows = GeneratedVoiceLine::query()->orderBy('agent')->orderBy('intent')->orderBy('line_key')->get();
        $stage1Generated = $rows->whereIn('reference_style_status', ['generated', 'fallback_generated'])->count();
        $stage2Generated = $rows->whereIn('kokoro_identity_status', ['generated', 'fallback_generated'])->count();
        $playbackReady = $rows->filter(fn (GeneratedVoiceLine $line): bool => (bool) ($line->reference_style_audio_path || $line->kokoro_identity_audio_path))->count();
        $durations = $rows->flatMap(fn (GeneratedVoiceLine $line): array => array_values(array_filter([
            $line->reference_style_duration_seconds,
            $line->kokoro_identity_duration_seconds,
        ], fn ($value) => $value !== null)))->values();

        $markdown = [
            '# ReaDirect Voice Line Database Report',
            '',
            '## Summary',
            '',
            '- Total lines discovered: '.$rows->count(),
            '- Total static lines: '.$rows->where('is_static', true)->where('is_defense_demo', false)->count(),
            '- Total dynamic templates: '.$rows->where('is_dynamic_template', true)->count(),
            '- Total defense fixture lines: '.$rows->where('is_defense_demo', true)->count(),
            '- Total Stage 1 reference-style files generated: '.$stage1Generated,
            '- Total Stage 2 Kokoro-identity files generated: '.$stage2Generated,
            '- Total final playback-ready files: '.$playbackReady,
            '- Current active stage: `'.config('readirect.voice_database.active_stage', 'reference_style').'`',
            '- Defense audio type: `'.config('readirect.voice_database.defense_audio_type', 'reference_style').'`',
            '- Stage 2 demo audio type: `'.config('readirect.voice_database.stage2_demo_audio_type', 'kokoro_identity').'`',
            '- Total Stage 1 failures: '.$rows->where('reference_style_status', 'failed')->count(),
            '- Total Stage 2 failures: '.$rows->where('kokoro_identity_status', 'failed')->count(),
            '- Total fallback-generated: '.$rows->where('status', 'fallback_generated')->count(),
            '- Average generated duration: '.($durations->isNotEmpty() ? round($durations->avg(), 3).'s' : 'none'),
            '- Outputs under 6 seconds: '.$durations->filter(fn ($value): bool => (float) $value < 6.0)->count(),
            '- Outputs between 6 and 9 seconds: '.$durations->filter(fn ($value): bool => (float) $value >= 6.0 && (float) $value <= 9.0)->count(),
            '- Outputs over 9 seconds: '.$durations->filter(fn ($value): bool => (float) $value > 9.0)->count(),
            '',
            '## Two-Stage Output Summary',
            '',
            '- `reference_style` generated files: '.$stage1Generated,
            '- `kokoro_identity` generated files: '.$stage2Generated,
            '- Defense playback uses `reference_style` first unless `READIRECT_TTS_ACTIVE_STAGE` is changed.',
            '- Stage 2 demo mode uses `kokoro_identity` first when `READIRECT_TTS_ACTIVE_STAGE=kokoro_identity`.',
            '- Lines falling back to Kokoro are marked with `fallback_generated` or a stage fallback reason.',
            '',
            '## Agent Breakdown',
            '',
        ];

        foreach ($rows->groupBy('agent') as $agent => $agentRows) {
            $markdown[] = "- {$agent}: ".$agentRows->count().' line(s)';
        }

        $markdown[] = '';
        $markdown[] = '## Intent Breakdown';
        $markdown[] = '';
        foreach ($rows->groupBy('intent') as $intent => $intentRows) {
            $markdown[] = '- '.($intent ?: 'none').': '.$intentRows->count().' line(s)';
        }

        $markdown[] = '';
        $markdown[] = '## Line Details';
        $markdown[] = '';
        $markdown[] = '| ID | Line key | Agent | Intent | Text | Original ref | Ref duration | Ref priority | Ref weight | Stage 1 path | Stage 1 duration | Stage 1 status | Stage 2 path | Stage 2 duration | Stage 2 status | Active playback file | Defense file | Stage 2 demo file | Error |';
        $markdown[] = '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |';

        foreach ($rows as $line) {
            $markdown[] = sprintf(
                '| %s | `%s` | `%s` | `%s` | %s | `%s` | %s | %s | %s | `%s` | %s | %s | `%s` | %s | %s | `%s` | `%s` | `%s` | %s |',
                $line->id,
                $line->line_key,
                $line->agent,
                $line->intent,
                $this->cell($line->text),
                $line->selected_original_reference_audio_path ?: '',
                $this->duration($line->selected_original_reference_duration_seconds),
                $line->selected_original_reference_priority ?: 'none',
                $line->selected_original_reference_weight ?: 0,
                $line->reference_style_audio_path ?: '',
                $this->duration($line->reference_style_duration_seconds),
                $line->reference_style_status,
                $line->kokoro_identity_audio_path ?: '',
                $this->duration($line->kokoro_identity_duration_seconds),
                $line->kokoro_identity_status,
                $line->active_audio_path ?: '',
                $line->defense_audio_path ?: '',
                $line->stage2_demo_audio_path ?: '',
                $this->cell($line->generation_error ?: $line->reference_style_error ?: $line->kokoro_identity_error ?: 'none'),
            );
        }

        Storage::disk('public')->put($root.'/VOICE_LINE_DATABASE_REPORT.md', implode(PHP_EOL, $markdown).PHP_EOL);
        Storage::disk('public')->put($root.'/voice_line_database_report.csv', $this->csv($rows));

        $this->info('Reports written to storage/app/public/'.$root);

        return self::SUCCESS;
    }

    private function duration(?float $seconds): string
    {
        return $seconds === null ? 'none' : number_format($seconds, 3).'s';
    }

    private function cell(?string $value): string
    {
        return str_replace('|', '\\|', trim((string) $value));
    }

    private function csv($rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'id',
            'line_key',
            'agent',
            'intent',
            'text',
            'selected_original_reference_audio_path',
            'selected_original_reference_duration_seconds',
            'selected_original_reference_priority',
            'selected_original_reference_weight',
            'reference_style_audio_path',
            'reference_style_duration_seconds',
            'reference_style_status',
            'kokoro_identity_audio_path',
            'kokoro_identity_duration_seconds',
            'kokoro_identity_status',
            'active_audio_path',
            'active_audio_type',
            'status',
            'generation_error',
        ]);

        foreach ($rows as $line) {
            fputcsv($handle, [
                $line->id,
                $line->line_key,
                $line->agent,
                $line->intent,
                $line->text,
                $line->selected_original_reference_audio_path,
                $line->selected_original_reference_duration_seconds,
                $line->selected_original_reference_priority,
                $line->selected_original_reference_weight,
                $line->reference_style_audio_path,
                $line->reference_style_duration_seconds,
                $line->reference_style_status,
                $line->kokoro_identity_audio_path,
                $line->kokoro_identity_duration_seconds,
                $line->kokoro_identity_status,
                $line->active_audio_path,
                $line->active_audio_type,
                $line->status,
                $line->generation_error,
            ]);
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }
}
