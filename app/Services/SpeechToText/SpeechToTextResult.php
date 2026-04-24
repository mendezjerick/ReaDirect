<?php

namespace App\Services\SpeechToText;

class SpeechToTextResult
{
    public function __construct(
        public readonly ?string $transcript,
        public readonly string $source = 'stt_placeholder',
        public readonly float $confidence = 0.0,
        public readonly array $metadata = []
    ) {
    }

    public function hasTranscript(): bool
    {
        return trim((string) $this->transcript) !== '';
    }
}
