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

