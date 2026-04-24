# ReaDirect Audio and Speech-to-Text Plan

Phase 6 adds browser audio capture as an input source while keeping official scoring rule-based.

## Current Phase 6 Behavior

- Learner pages use the browser `MediaRecorder` API through `AudioRecorder.vue`.
- Recordings are previewed in the browser before submission.
- Manual transcript input remains required for scored oral-reading items.
- The transcript is scored by the existing answer-matching and scoring services.
- Audio files are stored in Laravel private storage, not in the public web directory.
- Audio metadata is stored in `audio_files`.
- Assessment and module responses can reference an `audio_file_id`.

## Storage Model

Audio files are stored on the Laravel `local` disk, which is configured to use private storage:

```text
storage/app/private/audio/learners/{learner_public_id}/...
```

The database stores metadata only:

- learner
- assessment attempt or module attempt
- response link when available
- private file path
- MIME type
- size
- SHA-256 file hash
- duration
- recording context
- sync status

Database backups do not include audio files. Back up private storage separately.

## Transcript Sources

`transcript_source` identifies where the scored text came from:

- `manual`: typed transcript or manual placeholder input
- `stt_placeholder`: mock STT output, not real ASR
- `teacher_review`: future teacher-reviewed transcript
- `future_asr`: reserved for later ASR integrations

If audio exists but transcript text is blank, the system should block scoring or mark the item pending. It must not silently score blank transcripts as incorrect.

## Mock STT Service

Phase 6 provides a service contract only:

- `SpeechToTextServiceInterface`
- `MockSpeechToTextService`
- `SpeechToTextResult`

The mock service does not perform real speech recognition. It returns no transcript unless a development placeholder is configured.

Configuration lives in `config/readirect.php`:

```php
'speech_to_text' => [
    'provider' => env('READIRECT_STT_PROVIDER', 'mock'),
    'timeout_seconds' => (int) env('READIRECT_STT_TIMEOUT', 30),
    'mock_transcript' => env('READIRECT_STT_MOCK_TRANSCRIPT'),
],
```

## Future STT Options

Possible Phase 7+ integrations:

- Whisper.cpp for local/offline transcription
- Vosk for lightweight offline ASR
- Python/FastAPI speech service
- Wav2Vec2 or phoneme-level models later

Any future STT provider must send only sanitized, necessary context and must not make official scoring or placement decisions.

## Privacy Notes

- Do not expose public storage URLs for learner audio.
- Use authorized controller routes for playback.
- Treat learner voice recordings as private learner data.
- Follow retention and deletion policy before real deployment.
- Encrypt backups for production or government/client handoff.

Official scoring remains deterministic and rule-based. Audio and STT only provide input text or review evidence.
