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

- Miss Vivian: `af_bella`, speed `0.97`
- Miss Ciel: `af_heart`, speed `0.94`
- Miss Estelle: `bf_isabella`, speed `0.93`

Miss Ciel is permanently mapped to `af_heart`. Do not configure or request a different Ciel voice.

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
TTS_DEBUG_LOGGING=true
TTS_AGENT_PROFILES_ENABLED=true
TTS_AGENT_SPEED_VIVIAN=0.97
TTS_AGENT_SPEED_CIEL=0.94
TTS_AGENT_SPEED_ESTELLE=0.93
TTS_TEXT_HUMANIZER_ENABLED=true
TTS_TEXT_HUMANIZER_MODE=friendly
TTS_TEXT_HUMANIZER_VARIATION_ENABLED=true
TTS_TEXT_HUMANIZER_LOGGING=true
TTS_DELIVERY_CONTROL_ENABLED=true
TTS_SAFE_CHUNKING_ENABLED=true
TTS_MIN_FRIENDLY_TOKENS=12
TTS_MAX_COACHING_SENTENCES=3
TTS_HUMANIZER_ENABLED=true
TTS_AUDIO_NORMALIZE_ENABLED=true
TTS_AUDIO_FADE_ENABLED=true
TTS_PAUSE_CONTROL_ENABLED=true
TTS_BREATHS_ENABLED=false
TTS_BREATHS_VOLUME=0.08
TTS_BREATHS_MIN_TEXT_LENGTH=80
```

Laravel calls `ReaDirect-TTS`, stores WAV bytes in private storage, and serves learner audio through `/agent-voice/{cacheKey}`. The browser never receives local file paths or the Python service generated-audio path.

## Client Handoff

The client should receive the Laravel app, the AI/ASR service, and the TTS service setup guide. The deployment computer must install Kokoro dependencies locally. Do not commit or package runtime-generated folders such as `.venv`, `generated_audio`, model caches, `node_modules`, or `vendor`.
