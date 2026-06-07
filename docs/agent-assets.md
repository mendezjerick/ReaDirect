# Agent Assets

`ReaDirect-IA` is the source of truth for Miss Ciel, Miss Vivian, and Miss
Estelle images and videos. ReaDirect builds media URLs from:

```dotenv
VITE_REA_AGENT_ASSET_BASE_URL=/ia-assets
```

Agent files are not committed to ReaDirect. The configured URL must expose the
contents of `../ReaDirect-IA/assets` as static files. Videos are served
directly by the web server or static host, not streamed through Laravel
controllers and not fetched from GitHub. Static serving avoids Laravel
application overhead and is the faster runtime path for these media files.

## Local Windows Setup

From the main `ReaDirect` directory, an administrator command prompt can
create a directory junction:

```cmd
mklink /J public\ia-assets ..\ReaDirect-IA\assets
```

PowerShell can create the equivalent junction:

```powershell
New-Item -ItemType Junction -Path ".\public\ia-assets" -Target "..\ReaDirect-IA\assets"
```

`public/ia-assets` is ignored by Git. A web server alias or another static
mount may be used instead.

## Production

Publish or mount `ReaDirect-IA/assets` at `/ia-assets`, or set
`VITE_REA_AGENT_ASSET_BASE_URL` to the deployed static asset URL before
building the frontend. Keep the files local to the deployment environment;
internet access is not required.

Only idle videos loop. Interaction videos play once, ignore new cues while
busy, and return to idle when they end. Current idle files are approximately
five seconds long. They can later be replaced by one-second minimal-motion
loops without code changes because playback does not depend on duration.

The actual Vivian idle filename is `videos/Vivian/v-idle.mp4`. Estelle has
`videos/Estelle/e-idle.mp4`; static PNGs remain error fallbacks for every
agent.

## Application Boundaries

Laravel continues to own learner content, attempts, dialogue/content banks,
ASR results, scoring, module placement, and final-assessment decisions. Phase
2 centralizes only frontend media paths and playback behavior. It does not
change ASR, TTS, scoring, database schema, or content-bank storage.
