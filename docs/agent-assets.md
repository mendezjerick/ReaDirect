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
busy, and return to idle when they end. PNG images are fallback media only.
The player keeps the idle media visible while an interaction video prepares,
so a slow or failed video does not produce an empty container.

The actual Vivian idle filename is `videos/Vivian/v-idle.mp4`. Estelle has
`videos/Estelle/e-idle.mp4`; static PNGs remain error fallbacks for every
agent.

## Phase 5 Interaction Ownership

Agent selection and action aliases are context-aware:

- Miss Vivian owns diagnostic and final-assessment task flow. Uploading,
  processing, and retry encouragement use Vivian thinking.
- Vivian uses `videos/Vivian/v-talk.mp4` while her assessment dialogue TTS is
  actively speaking. Processing and retry cues continue to use
  `videos/Vivian/v-think.mp4`.
- Estelle uses `videos/Estelle/e-talk.mp4` for general spoken dialogue.
  Explicit results, summary, routing, placement, and recommendation cues keep
  using Estelle's results videos with higher priority.
- Miss Ciel owns module practice and mastery tutoring. Thinking is used for
  processing, talk while tutor TTS is actively speaking, happy for correct
  answers, confused for learner-facing errors or unclear/invalid results,
  advise for hints and retries, and clap for strong or section-complete
  feedback.
- Miss Estelle owns routing, placement, recommendations, summaries, and
  result presentation. These contexts use Estelle results.

Congrats is accepted only when the calling final-assessment or completion
screen explicitly allows it. Ordinary tasks, summaries, module completion,
and correct answers cannot request congrats.

The player preserves the no-interrupt/no-queue rule. A cue received while an
interaction is preparing or playing is discarded.

## Preloading

The homepage begins preloading only after Start Reading or a dashboard entry
is clicked. The preload list comes from the centralized registry and includes
all idle videos, interaction videos, and PNG fallbacks. The CSS book loader
stays visible for at least two seconds, with per-file and global timeouts so
navigation cannot be blocked indefinitely.

## Application Boundaries

Laravel continues to own learner content, attempts, dialogue/content banks,
ASR results, scoring, module placement, and final-assessment decisions. Phase
5 centralizes only frontend media selection and playback behavior. It does
not change ASR, TTS, scoring, database schema, or content-bank storage.
