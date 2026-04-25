<?php

namespace App\Services\SpeechToText;

use App\Models\AudioFile;
use App\Services\STT\AudioTranscriptionService;

class ConfiguredSpeechToTextService implements SpeechToTextServiceInterface
{
    public function __construct(private readonly AudioTranscriptionService $transcription)
    {
    }

    public function transcribe(AudioFile $audioFile): SpeechToTextResult
    {
        $result = $this->transcription->transcribeAudioFile($audioFile);

        return new SpeechToTextResult(
            transcript: $result->transcript,
            source: $result->source,
            confidence: $result->confidence,
            metadata: $result->metadata + ['error' => $result->error],
        );
    }
}
