<?php

namespace App\Services\STT;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class WhisperCppSTTService implements SpeechToTextServiceInterface
{
    public function __construct(private readonly TranscriptSanitizer $sanitizer)
    {
    }

    public function transcribeAudio(string $filePath): SpeechToTextResult
    {
        if (! config('stt.whisper_cpp.enabled')) {
            return $this->failure('Whisper.cpp STT is disabled.');
        }

        if (! is_file($filePath)) {
            return $this->failure('Audio file was not found.');
        }

        $modelPath = config('stt.whisper_cpp.model_path');

        if (! $modelPath || ! is_file($modelPath)) {
            return $this->failure('Whisper.cpp model file is not configured.');
        }

        $workingFile = $filePath;
        $temporaryFile = null;

        try {
            if (config('stt.whisper_cpp.convert_to_wav')) {
                $temporaryFile = $this->convertToWav($filePath);
                $workingFile = $temporaryFile;
            }

            $transcript = $this->runWhisper($workingFile, $modelPath);
            $transcript = $this->sanitizer->sanitize($transcript);

            return new SpeechToTextResult(
                transcript: $transcript,
                confidence: $transcript === '' ? 0.0 : 0.75,
                phonemes: null,
                timestamps: null,
                error: null,
                source: 'stt_auto',
                metadata: [
                    'provider' => 'whisper_cpp',
                    'real_asr' => true,
                ],
            );
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        } finally {
            if ($temporaryFile && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    public function transcribeAudioChunked(string $filePath, array $options = []): SpeechToTextResult
    {
        return $this->transcribeAudio($filePath);
    }

    private function runWhisper(string $filePath, string $modelPath): string
    {
        $outputBase = tempnam(sys_get_temp_dir(), 'readirect-whisper-');

        if ($outputBase === false) {
            throw new \RuntimeException('Could not create temporary STT output file.');
        }

        @unlink($outputBase);

        $command = [
            config('stt.whisper_cpp.binary_path'),
            '-m',
            $modelPath,
            '-f',
            $filePath,
            '-l',
            config('stt.language', 'en'),
            '-otxt',
            '-of',
            $outputBase,
        ];

        $extraArgs = trim((string) config('stt.whisper_cpp.extra_args', ''));

        if ($extraArgs !== '') {
            array_push($command, ...str_getcsv($extraArgs, ' '));
        }

        $process = new Process($command);
        $process->setTimeout((int) config('stt.timeout_seconds', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $outputFile = $outputBase.'.txt';
        $output = is_file($outputFile) ? file_get_contents($outputFile) : $process->getOutput();

        if (is_file($outputFile)) {
            @unlink($outputFile);
        }

        return (string) $output;
    }

    private function convertToWav(string $filePath): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'readirect-stt-');

        if ($temporaryFile === false) {
            throw new \RuntimeException('Could not create temporary WAV file.');
        }

        @unlink($temporaryFile);
        $temporaryFile .= '.wav';

        $process = new Process([
            config('stt.whisper_cpp.ffmpeg_path'),
            '-y',
            '-i',
            $filePath,
            '-ar',
            (string) config('stt.sample_rate_hz', 16000),
            '-ac',
            '1',
            $temporaryFile,
        ]);
        $process->setTimeout((int) config('stt.timeout_seconds', 30));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $temporaryFile;
    }

    private function failure(string $message): SpeechToTextResult
    {
        return new SpeechToTextResult(
            transcript: null,
            confidence: 0.0,
            error: $this->sanitizer->safeError($message),
            source: 'stt_auto',
            metadata: [
                'provider' => 'whisper_cpp',
                'real_asr' => true,
            ],
        );
    }
}
