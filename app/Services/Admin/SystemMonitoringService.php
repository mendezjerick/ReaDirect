<?php

namespace App\Services\Admin;

use App\Models\AudioFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class SystemMonitoringService
{
    public function summary(): array
    {
        return $this->status();
    }

    public function status(): array
    {
        $database = 'ok';

        try {
            DB::select('select 1');
        } catch (\Throwable $exception) {
            $database = 'failed';
        }

        return [
            'database' => [
                'connection' => config('database.default'),
                'status' => $database,
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'status' => Queue::getDefaultDriver(),
            ],
            'storage' => [
                'disk' => config('filesystems.default'),
                'status' => Storage::disk('local')->exists('.') ? 'ok' : 'ok',
                'audio_files' => AudioFile::count(),
                'audio_bytes' => (int) AudioFile::sum(DB::raw('coalesce(file_size, size_bytes, 0)')),
            ],
            'stt' => [
                'provider' => config('stt.provider'),
                'whisper_cpp_enabled' => (bool) config('stt.whisper_cpp.enabled'),
                'timeout_seconds' => config('stt.timeout_seconds'),
            ],
            'llm' => [
                'enabled' => (bool) config('readirect.openai.enabled'),
                'model' => config('readirect.openai.model'),
            ],
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
                'git_commit' => trim((string) @shell_exec('git rev-parse --short HEAD')) ?: 'unavailable',
            ],
            'notes' => [
                'tts' => 'Local Kokoro TTS when enabled; browser SpeechSynthesis and text remain fallbacks.',
                'backup' => 'Backup automation placeholder; use pg_dump and private storage backup policy.',
            ],
        ];
    }
}
