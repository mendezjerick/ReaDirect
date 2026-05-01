# ReaDirect AI ASR Environment Guide

ReaDirect Laravel delegates ASR to the separate ReaDirect-AI-ASR FastAPI service. The active ASR architecture is Wav2Vec2-only.

Laravel does not load or run Wav2Vec2 models locally. Model paths are owned by the AI service and may be displayed in Laravel only when reported by FastAPI health/version metadata.

## Laravel `.env`

```dotenv
READIRECT_AI_ENABLED=true
AI_ASR_SERVICE_URL=http://127.0.0.1:8001
READIRECT_AI_BASE_URL=http://127.0.0.1:8001
READIRECT_AI_API_TOKEN=
READIRECT_AI_TIMEOUT_SECONDS=60
READIRECT_AI_HEALTH_ENDPOINT=/health
READIRECT_AI_VERSION_ENDPOINT=/version
READIRECT_AI_ANALYZE_AUDIO_ENDPOINT=/analyze-audio
READIRECT_AI_ANALYZE_TEXT_ENDPOINT=/analyze-text
READIRECT_AI_RECOMMEND_NEXT_ENDPOINT=/recommend-next
READIRECT_AI_CONTENT_ITEM_ENDPOINT=/content-item
READIRECT_AI_FALLBACK_TO_STT=true
READIRECT_AI_USE_MANUAL_TRANSCRIPT=true
READIRECT_AI_ADMIN_DEBUG=true
ENABLE_ASR_DEBUG_METADATA=true
USE_CORRECTED_TRANSCRIPT_FOR_SCORING=true
USE_DISPLAYED_TRANSCRIPT_FOR_LEARNER_UI=true
ASR_ARCHITECTURE=wav2vec2_only
```

`AI_ASR_SERVICE_URL` is the preferred ASR service URL. `READIRECT_AI_BASE_URL` remains supported for existing environments.

## Expected AI Runtime

The admin dashboard should report:

- Active ASR Architecture: Wav2Vec2-only ASR runtime
- ASR Model: Fine-tuned Wav2Vec2 mixed model, or the model name reported by FastAPI
- Phoneme Support: Wav2Vec2 phoneme model, if reported
- Correction Layer: expected-centric acoustic-phonetic scoring
- Whisper Runtime: removed

If FastAPI omits a metadata field, Laravel displays `Not reported` instead of failing.

## Request/Response Contract

Laravel sends audio plus scoring context when available:

- `expected_text`
- `prompt_type`
- `activity_type`
- `module_type`
- `assessment_type`
- `item_id`
- `learner_id`
- `attempt_id`
- `current_scoring_context`

Laravel consumes these transcript fields in order:

- Scoring: `corrected_transcript`, then `transcript`, then `raw_transcript`
- Learner UI: `displayed_transcript`, then `corrected_transcript`, then `transcript`, then `raw_transcript`
- Admin/debug: raw, Wav2Vec2, corrected, displayed, WER/CER, phoneme, acceptance, normalization, and debug metadata

For sentence prompts, Laravel does not force the displayed transcript to the expected sentence. It uses the AI-provided Wav2Vec2 transcript/correction and existing sentence scoring rules.

## Troubleshooting

If the admin banner is disconnected, start the FastAPI service and confirm Laravel can reach `AI_ASR_SERVICE_URL`.

If learner ASR fails, Laravel should show a learner-safe error and log developer details. Failed AI calls must not mark an answer correct automatically.
