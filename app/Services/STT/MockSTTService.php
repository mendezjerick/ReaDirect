<?php

namespace App\Services\STT;

class MockSTTService implements SpeechToTextServiceInterface
{
    public function transcribeAudio(string $filePath, array $options = []): SpeechToTextResult
    {
        return new SpeechToTextResult(
            transcript: config('stt.mock.transcript'),
            confidence: config('stt.mock.transcript') ? 0.5 : 0.0,
            error: null,
            source: 'stt_placeholder',
            metadata: [
                'provider' => 'mock',
                'real_asr' => false,
            ],
        );
    }

    public function transcribeAudioChunked(string $filePath, array $options = []): SpeechToTextResult
    {
        return $this->transcribeAudio($filePath, $options);
    }
}
