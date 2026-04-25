# STT Integration

Phase 9 adds a real speech-to-text pipeline while keeping all ReaDirect scoring and placement decisions rule-based.

## Current Behavior

Recorded learner audio is stored in Laravel private storage. When audio is submitted, ReaDirect resolves the transcript in this order:

1. Manual transcript typed by the learner or teacher/developer.
2. STT transcript generated from the private audio file.
3. If no transcript is available, scoring is blocked and the learner is asked to complete the item.

The resolved transcript is then scored by the existing rule-based services, including `AnswerMatchingService`, `CrlaScoringService`, and `ModuleScoringService`.

STT does not decide correctness, score, reading level, module placement, mastery, or recommendations.

## Service Structure

Primary Phase 9 services live in `app/Services/STT`:

- `SpeechToTextServiceInterface`
- `SpeechToTextResult`
- `MockSTTService`
- `WhisperCppSTTService`
- `TranscriptSanitizer`
- `AudioTranscriptionService`
- `TranscriptResolver`

The older Phase 6 namespace `app/Services/SpeechToText` remains as a compatibility wrapper.

## Configuration

Configuration is in `config/stt.php`.

Example `.env` values:

```env
STT_PROVIDER=whisper_cpp
STT_TIMEOUT_SECONDS=30
STT_SAMPLE_RATE_HZ=16000
STT_LANGUAGE=en
STT_WHISPER_CPP_ENABLED=true
STT_WHISPER_CPP_BINARY_PATH=whisper-cli
STT_WHISPER_CPP_MODEL_PATH=C:\models\ggml-base.en.bin
STT_FFMPEG_PATH=ffmpeg
STT_CONVERT_TO_WAV=false
```

For development without local ASR:

```env
STT_PROVIDER=mock
STT_MOCK_TRANSCRIPT=
```

When the mock transcript is empty, the app safely falls back to manual transcript input.

## Whisper.cpp Adapter

`WhisperCppSTTService` runs a local `whisper.cpp` command. It expects:

- a local `whisper-cli` or equivalent binary
- a local English model file
- optional `ffmpeg` if conversion to 16 kHz mono WAV is enabled

No audio is sent outside the server by this adapter.

## Database Fields

Audio metadata:

- `audio_files.transcript`
- `audio_files.stt_confidence`
- `audio_files.stt_phonemes`
- `audio_files.stt_timestamps`
- `audio_files.stt_error`
- `audio_files.stt_completed_at`

Response metadata:

- `assessment_task_responses.learner_transcript`
- `assessment_task_responses.stt_confidence`
- `module_activity_responses.learner_transcript`
- `module_activity_responses.stt_confidence`
- `transcript_source`: `manual`, `stt_auto`, or `teacher_review`

## Teacher Review

Teacher review pages show:

- audio playback
- transcript source
- transcript text
- STT confidence
- transcript correction box

Saving a teacher-reviewed transcript marks `transcript_source = teacher_review`. It does not automatically rescore or change official decisions in Phase 9.

## Privacy Rules

- Audio files stay in private Laravel storage.
- Raw audio URLs are not public.
- STT errors are sanitized before storage.
- Raw learner audio and private transcripts should not be written to logs.
- External STT providers should not be enabled unless explicitly configured and approved.

## Manual Verification

1. Set `STT_PROVIDER=mock` and `STT_MOCK_TRANSCRIPT=cat`.
2. Run `php artisan config:clear`.
3. Record audio on a learner item and leave manual text blank.
4. Submit the page.
5. Confirm the response uses `transcript_source = stt_auto`.
6. Open teacher review and confirm transcript, confidence, and playback appear.
7. Edit the transcript as teacher and save.
8. Confirm the transcript changes to `teacher_review` and score remains unchanged.

For Whisper.cpp:

1. Install/build whisper.cpp locally.
2. Download an English model file.
3. Configure `STT_WHISPER_CPP_BINARY_PATH` and `STT_WHISPER_CPP_MODEL_PATH`.
4. Set `STT_PROVIDER=whisper_cpp` and `STT_WHISPER_CPP_ENABLED=true`.
5. Submit a recording and verify a transcript is generated.

## Troubleshooting

- `Whisper.cpp STT is disabled.`: Set `STT_WHISPER_CPP_ENABLED=true`.
- `model file is not configured`: Set `STT_WHISPER_CPP_MODEL_PATH` to an existing model.
- Timeout: increase `STT_TIMEOUT_SECONDS` or use a smaller model.
- Blank transcript: keep manual fallback enabled and check audio quality.
- `ffmpeg` error: install ffmpeg or set `STT_CONVERT_TO_WAV=false` if whisper.cpp accepts the uploaded format.
