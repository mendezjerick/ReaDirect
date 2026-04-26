<?php

namespace App\Services\STT;

use App\Models\AudioFile;
use Illuminate\Support\Facades\Storage;

class AudioTranscriptionService
{
    public function __construct(
        private readonly SpeechToTextServiceInterface $speechToText,
        private readonly TranscriptSanitizer $sanitizer
    ) {
    }

    public function transcribeAudioFile(AudioFile $audioFile, array $options = []): SpeechToTextResult
    {
        $disk = $audioFile->disk ?: 'local';
        $path = $audioFile->file_path ?: $audioFile->path;
        $absolutePath = Storage::disk($disk)->path($path);
        $result = $this->speechToText->transcribeAudio($absolutePath, $options);
        $transcript = $this->sanitizer->sanitize($result->transcript);
        $result = $result->withTranscript($transcript === '' ? null : $transcript);

        $audioFile->update([
            'transcript' => $result->transcript,
            'stt_confidence' => $result->confidence,
            'stt_phonemes' => $result->phonemes,
            'stt_timestamps' => $result->timestamps,
            'stt_error' => $this->sanitizer->safeError($result->error),
            'stt_completed_at' => now(),
            'metadata' => array_merge($audioFile->metadata ?? [], [
                'stt_provider' => $result->metadata['provider'] ?? config('stt.provider'),
                'stt_real_asr' => $result->metadata['real_asr'] ?? false,
                'stt_source' => $result->source,
            ]),
        ]);

        return $result;
    }
}
