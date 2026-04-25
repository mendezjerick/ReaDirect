<?php

namespace App\Services\STT;

class SpeechToTextResult
{
    public function __construct(
        public readonly ?string $transcript = null,
        public readonly float $confidence = 0.0,
        public readonly ?array $phonemes = null,
        public readonly mixed $timestamps = null,
        public readonly ?string $error = null,
        public readonly string $source = 'stt_auto',
        public readonly array $metadata = []
    ) {
    }

    public function hasTranscript(): bool
    {
        return trim((string) $this->transcript) !== '';
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    public function withTranscript(?string $transcript): self
    {
        return new self(
            transcript: $transcript,
            confidence: $this->confidence,
            phonemes: $this->phonemes,
            timestamps: $this->timestamps,
            error: $this->error,
            source: $this->source,
            metadata: $this->metadata,
        );
    }

    public function toArray(): array
    {
        return [
            'transcript' => $this->transcript,
            'confidence' => $this->confidence,
            'phonemes' => $this->phonemes,
            'timestamps' => $this->timestamps,
            'error' => $this->error,
            'source' => $this->source,
            'metadata' => $this->metadata,
        ];
    }
}
