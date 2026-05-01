# Main Repo AI Import Guide

This Laravel repository integrates with the ReaDirect-AI-ASR FastAPI service over HTTP.

The active ASR architecture is Wav2Vec2-only. Do not copy model artifacts into Laravel. Keep ASR and phoneme model files in the AI repository and expose their status through FastAPI health/version metadata.

Laravel integration points:

- Configure `AI_ASR_SERVICE_URL` or `READIRECT_AI_BASE_URL`.
- Send `expected_text` and prompt/activity/module metadata with ASR requests.
- Use `corrected_transcript` for scoring when available.
- Use `displayed_transcript` for learner UI when available.
- Preserve `raw_transcript`, Wav2Vec2 metadata, scoring metadata, and phoneme evidence for admin QA.
