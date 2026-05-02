# Laravel AI Integration

ReaDirect uses a separate `ReaDirect-AI-ASR` FastAPI service for AI/ASR analysis. Laravel remains responsible for authentication, learner flow, database storage, official scoring, progression rules, and UI feedback.

## Runtime Architecture

1. Laravel stores learner audio using the existing private audio upload flow.
2. Laravel sends audio path, expected answer, accepted answers, prompt ID, and context to the AI service.
3. The AI service transcribes/analyzes the response and returns advisory signals.
4. Laravel stores the AI transcript/error/similarity fields.
5. Laravel’s existing scoring services remain the official scorer.

Students do not call the AI service directly. Laravel calls it server-to-server.

## Local Run

Terminal 1:

```powershell
cd ..\ReaDirect-AI-ASR
python scripts\validate_ai_service_startup.py
python -m uvicorn api.main:app --reload --host 127.0.0.1 --port 8001
```

Terminal 2:

```powershell
cd ..\ReaDirect
php artisan serve
npm run dev
```

Laravel `.env`:

```env
READIRECT_AI_ENABLED=true
READIRECT_AI_BASE_URL=http://127.0.0.1:8001
```

## Fallback Behavior

If the AI service is disabled or offline, Laravel falls back to the existing STT/manual transcript flow when configured:

```env
READIRECT_AI_FALLBACK_TO_STT=true
READIRECT_AI_USE_MANUAL_TRANSCRIPT=true
```

AI failure should not automatically penalize the learner. The response can be marked for retry/review while official scoring remains Laravel-owned.

## Stored AI Fields

Laravel stores searchable advisory fields such as:

- `ai_transcript`
- `ai_normalized_transcript`
- `ai_similarity_label`
- `ai_character_similarity`
- `ai_phoneme_similarity`
- `ai_error_type`
- `ai_feedback_hint`
- `ai_skill_signal`
- `ai_target_phoneme`
- `ai_response`

Admin/debug views may show these details. Student-facing views should only show safe feedback such as `learner_safe_summary` or coach messages.

## Sentence Accuracy And Fluency Metadata

Sentence reading remains scored in Laravel from the transcript chosen by the existing resolver. Laravel does not force a sentence `displayed_transcript` to the `expected_text`; letter/word expected-centric correction is separate from sentence scoring.

For sentence reading, Laravel normalizes the expected sentence and actual transcript into words, then runs word-level dynamic-programming alignment. The stored metadata includes the alignment path and operation counts:

- `alignment`
- `substitutions`
- `deletions`
- `insertions`
- `correct_words`
- `total_expected_words`
- `wer`
- `wer_accuracy_percentage`
- `text_accuracy_percentage`

WER is calculated from the explicit alignment counts:

```text
WER = (substitutions + deletions + insertions) / total_expected_words
```

Laravel also stores rate metrics when recording duration is available:

```text
WPM = actual transcript word count / duration in minutes
WCPM = alignment match count / duration in minutes
```

If duration is missing or invalid, `wpm`, `wcpm`, and `words_per_second` are `null` and a warning is added. The existing rushed/slow logic is retained as a pacing heuristic only. It returns `words_per_second`, `pacing_label`, `rushed`, `slow`, and `pacing_warning`; it is not full prosody analysis.

Fluency is additional metadata and does not replace the official sentence score. Default configurable weights are:

- WCPM score: 35%
- Accuracy score: 35%
- Pacing score: 15%
- Pause score: 10%
- Completion score: 5%

When the AI service returns `pause_metrics`, Laravel consumes:

- `pause_count`
- `long_pause_count`
- `very_long_pause_count`
- `longest_pause_seconds`
- `pause_ratio`
- `total_pause_seconds`

These produce `pause_score`, `pause_metrics_available`, and `long_pause_warning`. If pause metrics are missing, Laravel uses a neutral pause score and sets `pause_metrics_available=false`.

Laravel also preserves AI uncertainty fields in sentence metadata:

- `retry_required`
- `uncertain`
- `uncertainty_reasons`
- `audio_quality`
- `learner_retry_message`

If `retry_required=true`, the current flow preserves existing score behavior but stores retry metadata and exposes safe learner guidance. Laravel does not implement ASR inference locally and does not train any model.

## Enriched Content

The enriched content ZIP from the AI repo is placed at:

```text
content-bank/import/readirect-enriched-content.zip
```

Reviewed enriched CSVs are placed under:

```text
database/seed-data/readirect/enriched/
```

The current seeders still read the original curated CSVs and supplement seeded records with enrichment metadata when available.

## External Files

See [AI_ASR_EXTERNAL_FILES_GUIDE.md](AI_ASR_EXTERNAL_FILES_GUIDE.md). Speechocean762 and model artifacts are not copied into the Laravel repo.
