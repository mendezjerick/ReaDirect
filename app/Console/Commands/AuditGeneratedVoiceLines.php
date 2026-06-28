<?php

namespace App\Console\Commands;

use App\Services\VoiceLines\VoiceLineCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditGeneratedVoiceLines extends Command
{
    protected $signature = 'readirect:voice-lines:audit {--path= : Output markdown path}';

    protected $description = 'Write an audit of discovered ReaDirect agent voice lines and database generation eligibility.';

    public function handle(VoiceLineCatalog $catalog): int
    {
        $path = $this->option('path') ?: base_path('../READIRECT_VOICE_LINE_DATABASE_AUDIT.md');
        $lines = [
            '# ReaDirect Voice Line Database Audit',
            '',
            'This audit covers the main ReaDirect Laravel/Vue repo and the ReaDirect-TTS repo.',
            '',
            '## Ownership Decision',
            '',
            '- The main ReaDirect app owns the generated voice line database, active playback stage, and learner playback resolution.',
            '- ReaDirect-TTS owns synthesis, reference selection, Kokoro identity references, IndexTTS2 generation, and runtime fallback.',
            '- Runtime synthesis remains available when a database line or generated audio file is missing.',
            '- Generic automatic prompt extension is disabled by default through `TTS_AUTO_PROMPT_EXTENSION_ENABLED=false`.',
            '',
            '## Important Source Locations',
            '',
            '| Repo | File | Purpose |',
            '| --- | --- | --- |',
            '| ReaDirect | `app/Services/VoiceLines/VoiceLineCatalog.php` | Database seed catalog for static lines, dynamic templates, and defense fixtures. |',
            '| ReaDirect | `app/Services/TTS/AgentTtsService.php` | Existing runtime TTS client; now checks database-generated audio first. |',
            '| ReaDirect | `app/Services/VoiceLines/VoiceLineService.php` | Resolves active stage and database audio fallback order. |',
            '| ReaDirect | `app/Agents/Ciel/CielDialogueCatalog.php` | Runtime Ciel dialogue fallback templates. |',
            '| ReaDirect-IA | `dialogue/ciel.yaml` | Loaded Ciel dialogue templates used by Laravel when available. |',
            '| ReaDirect-TTS | `curated_agent_lines.py` | TTS-side curated fallback catalog and known legacy text mapping. |',
            '| ReaDirect-TTS | `tts_humanizer.py` | Old generic humanizer; automatic extension is disabled unless explicitly enabled. |',
            '| ReaDirect-TTS | `expressive_references.py` | Duration-weighted reference selection. |',
            '| ReaDirect-TTS | `tts_service.py` | Runtime `/synthesize` endpoint and batch voice-line generation endpoint. |',
            '',
            '## Line Registry Classification',
            '',
            '| Line key | Agent | Intent | Source file | Static | Dynamic template | Defense demo | Safe to pre-generate | Stage 1 + Stage 2 | Context | Text |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($catalog->allSeedLines() as $entry) {
            $safe = ($entry['is_dynamic_template'] ?? false) ? 'template only' : 'yes';
            $twoStage = ($entry['generate_two_stage'] ?? false) ? 'yes' : 'no';
            $lines[] = sprintf(
                '| `%s` | `%s` | `%s` | `%s` | %s | %s | %s | %s | %s | %s | %s |',
                $entry['line_key'],
                $entry['agent'],
                $entry['intent'],
                str_replace('|', '\\|', $entry['source_file']),
                ($entry['is_static'] ?? false) ? 'yes' : 'no',
                ($entry['is_dynamic_template'] ?? false) ? 'yes' : 'no',
                ($entry['is_defense_demo'] ?? false) ? 'yes' : 'no',
                $safe,
                $twoStage,
                str_replace('|', '\\|', $entry['context']),
                str_replace('|', '\\|', $entry['text']),
            );
        }

        $lines[] = '';
        $lines[] = '## Dynamic and Protected Handling';
        $lines[] = '';
        $lines[] = '- Dynamic transcript and target-word patterns are registered as templates.';
        $lines[] = '- Defense fixture variants are pre-generated for known demo words and names.';
        $lines[] = '- Unknown runtime transcript values fall back to runtime TTS and do not block learner flow.';
        $lines[] = '- Protected ASR transcript, learner transcript, target words, score values, and answer choices are not generically rewritten.';

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
        $this->info("Voice line audit written to {$path}");

        return self::SUCCESS;
    }
}
