<?php

namespace App\Services\STT;

interface SpeechToTextServiceInterface
{
    public function transcribeAudio(string $filePath, array $options = []): SpeechToTextResult;

    public function transcribeAudioChunked(string $filePath, array $options = []): SpeechToTextResult;
}
