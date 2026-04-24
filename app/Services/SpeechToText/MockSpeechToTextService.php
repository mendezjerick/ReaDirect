<?php

namespace App\Services\SpeechToText;

use App\Models\AudioFile;

class MockSpeechToTextService implements SpeechToTextServiceInterface
{
    public function transcribe(AudioFile $audioFile): SpeechToTextResult
    {
        return new SpeechToTextResult(
            transcript: config('readirect.speech_to_text.mock_transcript'),
            source: 'stt_placeholder',
            confidence: 0.0,
            metadata: [
                'provider' => 'mock',
                'audio_file_public_id' => $audioFile->public_id,
                'real_asr' => false,
            ]
        );
    }
}
