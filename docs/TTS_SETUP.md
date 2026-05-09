# ReaDirect Local TTS Setup

ReaDirect uses a sibling local FastAPI service named `ReaDirect-TTS` for natural agent voices. Kokoro is the only spoken TTS provider. If Kokoro or the TTS service is unavailable, learner pages show the text-only agent message.

Expected local structure:

```text
C:\Users\Lost\Documents\holder-ReaDirect
├── ReaDirect
├── ReaDirect-AI-ASR
└── ReaDirect-TTS
```

Selected Kokoro voices:

- Miss Vivian: `af_bella`, speed `0.95`
- Miss Ciel: `af_heart`, speed `1.00`
- Miss Estelle: `bf_isabella`, speed `0.95`

## ReaDirect-TTS Setup

```powershell
cd "C:\Users\Lost\Documents\holder-ReaDirect\ReaDirect-TTS"
py -m venv .venv
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip setuptools wheel
python -m pip install torch --index-url https://download.pytorch.org/whl/cpu
python -m pip install -r requirements.txt
```

Run:

```powershell
python tts_service.py
```

or double-click:

```text
start_tts_service.bat
```

Health check:

```text
http://127.0.0.1:8002/health
```

## Laravel `.env`

```env
TTS_ENABLED=true
TTS_PROVIDER=kokoro
TTS_BASE_URL=http://127.0.0.1:8002
TTS_TIMEOUT_SECONDS=10
TTS_FALLBACK_TO_TEXT=true
TTS_CACHE_ENABLED=true
TTS_DEBUG=false
TTS_VOICE_VIVIAN=af_bella
TTS_VOICE_CIEL=af_heart
TTS_VOICE_ESTELLE=bf_isabella
TTS_SPEED_VIVIAN=0.95
TTS_SPEED_CIEL=1.00
TTS_SPEED_ESTELLE=0.95
```

Laravel calls `ReaDirect-TTS`, stores WAV bytes in private storage, and serves learner audio through `/agent-voice/{cacheKey}`. The browser never receives local file paths or the Python service generated-audio path.

## Client Handoff

The client should receive the Laravel app, the AI/ASR service, and the TTS service setup guide. The deployment computer must install Kokoro dependencies locally. Do not commit or package runtime-generated folders such as `.venv`, `generated_audio`, model caches, `node_modules`, or `vendor`.
