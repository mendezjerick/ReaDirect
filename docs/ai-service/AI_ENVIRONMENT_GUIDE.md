# ReaDirect AI Environment Guide

Use this guide when the AI service banner is confusing or the client is unsure which `.env` values to use.

## Quick Decision

For normal local development with the real fine-tuned Whisper model:

- Laravel runs from `ReaDirect`.
- FastAPI runs from `ReaDirect-AI-ASR`.
- Laravel points to FastAPI at `http://127.0.0.1:8001`.
- FastAPI uses `hf_whisper_local`.
- The model lives in `ReaDirect-AI-ASR/model_artifacts/readirect-whisper-base-en-v1-hf`.

If the banner says `ASR: mock`, FastAPI is connected but not using real Whisper transcription.

## Laravel `.env`

Set these in the main Laravel repo, `ReaDirect/.env`:

```env
READIRECT_AI_ENABLED=true
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
```

What these mean:

- `READIRECT_AI_ENABLED=true`: Laravel will call the AI service.
- `READIRECT_AI_BASE_URL=http://127.0.0.1:8001`: FastAPI service URL.
- `READIRECT_AI_API_TOKEN`: shared secret if FastAPI auth is enabled. Leave blank only for local trusted development.
- `READIRECT_AI_FALLBACK_TO_STT=true`: Laravel may use its fallback STT if AI is unavailable.
- `READIRECT_AI_USE_MANUAL_TRANSCRIPT=true`: manual transcript can be used when available.
- `READIRECT_AI_ADMIN_DEBUG=true`: admin/debug screens can show AI diagnostic payloads.

After changing Laravel `.env`, run:

```powershell
php artisan config:clear
```

## FastAPI AI Settings

FastAPI reads `ReaDirect-AI-ASR/configs/service_config.yaml` and environment variables. Environment variables override the YAML file.

Recommended real local model settings:

```env
ASR_PROVIDER=hf_whisper_local
ASR_HF_MODEL_PATH=model_artifacts/readirect-whisper-base-en-v1-hf
ASR_DEVICE=cuda
ASR_USE_FP16=true
ASR_LANGUAGE=en
ASR_TASK=transcribe
```

CPU fallback if CUDA is unavailable:

```env
ASR_PROVIDER=hf_whisper_local
ASR_HF_MODEL_PATH=model_artifacts/readirect-whisper-base-en-v1-hf
ASR_DEVICE=cpu
ASR_USE_FP16=false
ASR_LANGUAGE=en
ASR_TASK=transcribe
```

Use `mock` only for tests or UI development:

```env
ASR_PROVIDER=mock
```

Do not use `mock` for real learner audio.

## FastAPI Security

For local development:

```env
API_AUTH_ENABLED=false
READIRECT_AI_API_TOKEN=
```

For client deployment:

```env
API_AUTH_ENABLED=true
READIRECT_AI_API_TOKEN=replace-with-a-long-random-secret
```

When `API_AUTH_ENABLED=true`, Laravel and FastAPI must use the same `READIRECT_AI_API_TOKEN`. Laravel sends it as:

```http
X-ReaDirect-AI-Token: <token>
```

## Content and Phoneme Files

FastAPI expects these paths:

```env
CONTENT_INDEX_PATH=data/manifests/content_index.csv
ENRICHED_CONTENT_INDEX_PATH=content_bank_enriched/enriched_content_index.csv
CMUDICT_DIR=external_datasets/cmudict
```

The AI service can run without Speechocean762 at production runtime. Speechocean762 is for research, evaluation, and fine-tuning workflows, not normal Laravel integration.

## How to Start Services

Start FastAPI from `ReaDirect-AI-ASR`:

```powershell
python scripts/validate_ai_service_startup.py
python -m uvicorn api.main:app --host 127.0.0.1 --port 8001
```

Start Laravel from `ReaDirect`:

```powershell
php artisan serve
npm run dev
```

## How to Verify

Check FastAPI directly:

```powershell
Invoke-RestMethod http://127.0.0.1:8001/health
Invoke-RestMethod http://127.0.0.1:8001/version
```

Expected real provider:

```json
"asr_provider": "hf_whisper_local"
```

Bad for production:

```json
"asr_provider": "mock"
```

Check Laravel:

- Open `/admin/dashboard`.
- The AI banner should say connected.
- The ASR value should be `hf_whisper_local` or another real provider, not `mock`.

## Common Problems

### Banner says AI service not connected

Check:

- FastAPI is running on port `8001`.
- `READIRECT_AI_BASE_URL` is correct.
- Firewall or port conflicts are not blocking `127.0.0.1:8001`.
- If auth is enabled, both repos share the same token.

### Banner says ASR: mock

FastAPI is running, but the real model is not selected. Set:

```env
ASR_PROVIDER=hf_whisper_local
```

Then restart FastAPI.

### Startup validation says model path missing

Confirm the model exists:

```text
ReaDirect-AI-ASR/model_artifacts/readirect-whisper-base-en-v1-hf
```

That folder should contain files such as `config.json`, `generation_config.json`, `model.safetensors`, `tokenizer.json`, and processor/tokenizer configs.

### CUDA error

Use CPU fallback:

```env
ASR_DEVICE=cpu
ASR_USE_FP16=false
```

This is slower but useful for troubleshooting.

## Authority Boundary

FastAPI handles:

- audio transcription
- phoneme analysis
- similarity signals
- feedback hints
- advisory recommendations

Laravel handles:

- official scores
- learner records
- progression and module placement
- admin and teacher dashboards
- database storage

The AI service should not be treated as the official scorer.
