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

Idle now uses the static PNG for each agent. The idle videos were intentionally
removed from ReaDirect-IA to reduce runtime and repository weight.

The runtime flow is:

```text
idle PNG -> interaction video -> idle PNG
```

Interaction videos play once, ignore new cues while loading or playing, and
return to the same agent PNG when they end. No action queue is maintained.
The PNG remains visible while an interaction video prepares, preventing an
empty media box. A failed or slow interaction stays on the idle PNG.

## Application Boundaries

Laravel continues to own learner content, attempts, dialogue/content banks,
ASR results, scoring, module placement, and final-assessment decisions. Phase
4 changes only frontend media paths, playback readiness, and preloading. It
does not change ASR, TTS, scoring, database schema, or content-bank storage.

## Phase 4 Preloading

The homepage does not preload agent media during its initial render. Preload
starts only after the user clicks:

- Start Reading
- Admin Dashboard, when available for the signed-in role
- Teacher Dashboard, when available for the signed-in role

The click shows a lightweight CSS/vector flipping-book screen, preloads all
known agent images and interaction videos, and then continues through the
existing Inertia route. The loader remains visible for at least 2000
milliseconds.
Individual failures are tolerated, and a global timeout prevents preloading
from blocking navigation indefinitely.

The preload service derives all URLs from the centralized media registry and
uses `VITE_REA_AGENT_ASSET_BASE_URL`. It caches completed URLs and shares one
batch promise so repeated or rapid clicks do not start duplicate downloads.

Current video filenames:

- Ciel: `c-thinking-1.mp4`, `c-thinking-2.mp4`, `c-thinking-3.mp4`,
  `c-happy.mp4`, `c-confused.mp4`, `c-advise.mp4`, `c-clap.mp4`,
  `c-congrats.mp4`
- Vivian: `v-thinking-1.mp4`, `v-thinking-2.mp4`, `v-congrats.mp4`
- Estelle: `e-results-1.mp4`, `e-results-2.mp4`, `e-congrats.mp4`

Deleted idle-video filenames must not be restored or referenced.
