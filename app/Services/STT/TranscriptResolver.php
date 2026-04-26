<?php

namespace App\Services\STT;

use App\Models\AudioFile;

class TranscriptResolver
{
    public function __construct(
        private readonly AudioTranscriptionService $transcription,
        private readonly TranscriptSanitizer $sanitizer
    ) {
    }

    public function resolve(?string $manualTranscript, ?AudioFile $audioFile, array $options = []): array
    {
        $manual = $this->sanitizer->sanitize($manualTranscript);

        if ($manual !== '') {
            return [
                'transcript' => $manual,
                'source' => 'manual',
                'confidence' => null,
                'stt_result' => null,
            ];
        }

        if (! $audioFile) {
            return [
                'transcript' => '',
                'source' => 'manual',
                'confidence' => null,
                'stt_result' => null,
            ];
        }

        $result = $this->transcription->transcribeAudioFile($audioFile, $options);

        return [
            'transcript' => $result->hasTranscript() ? $result->transcript : '',
            'source' => $result->hasTranscript() ? 'stt_auto' : 'manual',
            'confidence' => $result->hasTranscript() ? $result->confidence : null,
            'stt_result' => $result,
        ];
    }
}
