# FastAPI AI Service

Laravel expects the separate ReaDirect-AI-ASR FastAPI service to provide Wav2Vec2-only ASR.

Start the AI service from the AI repository and point Laravel at it with:

```dotenv
AI_ASR_SERVICE_URL=http://127.0.0.1:8001
READIRECT_AI_BASE_URL=http://127.0.0.1:8001
ASR_ARCHITECTURE=wav2vec2_only
```

The service should expose:

- `GET /health`
- `GET /version`
- `POST /analyze-audio`
- `POST /analyze-text`
- `POST /recommend-next`
- `POST /content-item`

Health/version metadata may include Wav2Vec2 model availability, phoneme model availability, correction-layer status, supported prompt types, thresholds, and loaded model paths. Laravel displays missing fields as `Not reported`.

Laravel does not perform ASR inference locally.
