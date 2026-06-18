# ReaDirect Automatic Ciel Listening Readiness

Date prepared: 2026-06-17

## Prompt 3 Implementation Update

Prompt 3 has now implemented toggleable Automatic Ciel Listening Mode in the Laravel repo for supported Ciel module activity and mastery pages. The original Prompt 2 readiness sections below are historical preparation notes. The current implementation details, files changed, state machine, test results, limitations, and rollback notes are documented in `docs/READIRECT_AUTOMATIC_CIEL_LISTENING_MODE.md`.

## 1. Summary of Prompt 2 Scope

Prompt 2 prepared extension points only.

Automatic Ciel Listening Mode was not implemented. The current manual recorder remains the default learner experience. No live microphone listening, live VAD, automatic recording loop, auto-submit, background microphone behavior, scoring change, progression change, Ciel behavior change, or new animation behavior was added.

The current baseline from `READIRECT_EXISTING_SYSTEM_AUDIT.md` remains authoritative:

- Laravel owns official scoring, progression, placement, mastery, learner flow, audio upload orchestration, Ciel rendering, IA client calls, and TTS proxy/cache.
- ReaDirect-AI-ASR owns speech analysis only.
- ReaDirect-IA owns constrained Ciel decisions only.
- Manual recording mode remains default.
- `c-congrats` remains restricted to final assessment completion.

## 2. What Changed in the Laravel Repo

Files changed:

- `database/migrations/2026_06_17_000001_create_learner_preferences_table.php`
- `app/Models/LearnerPreference.php`
- `app/Models/Learner.php`
- `app/Services/LearnerListeningModeService.php`
- `app/Http/Controllers/Learner/LearnerDashboardController.php`
- `resources/js/Pages/Learner/Dashboard.vue`
- `resources/js/Composables/useAutomaticCielListeningSession.js`
- `tests/Unit/LearnerListeningModeServiceTest.php`
- `docs/READIRECT_AUTOMATIC_LISTENING_READINESS.md`

Learner preference foundation:

- Added `learner_preferences` table.
- Added one row per learner through unique `learner_id`.
- Added `listening_mode` string field.
- Supported values are defined in `LearnerListeningModeService`:
  - `manual`
  - `automatic_ciel`
- Default is `manual`.
- Existing learners without a preference row resolve to `manual`.
- No dashboard route or learner-facing toggle was added.
- No existing learner is automatically opted into `automatic_ciel`.

Backend resolver:

- Added `LearnerListeningModeService`.
- It resolves missing/unknown modes to `manual`.
- It validates allowed modes before saving through `setForLearner`.
- It exposes passive props for future UI work:
  - `current`
  - `default`
  - `allowed`
  - `automatic_mode_available`

Dashboard props:

- `LearnerDashboardController` now passes `listeningMode` props to the learner dashboard.
- The Vue dashboard UI was not changed.
- `automatic_mode_available` is currently `false` so Prompt 2 does not expose or enable the future mode.

Frontend placeholder:

- Added `resources/js/Composables/useAutomaticCielListeningSession.js`.
- It exports future state names only:
  - `IDLE`
  - `REQUESTING_PERMISSION`
  - `LISTENING`
  - `RECORDING_SPEECH`
  - `SUBMITTING`
  - `PROCESSING`
  - `CIEL_SPEAKING`
  - `TEACHING_MODE`
  - `WAITING_FOR_RETRY`
  - `COMPLETED`
  - `ERROR`
- It is not imported into production flow.
- It does not request microphone access, start `MediaRecorder`, submit audio, or drive Ciel behavior.

Default behavior confirmation:

- `AudioRecorder.vue` was not changed.
- `AssessmentModeService` still forces `autoTranscribeOnStop=false` and `requireReviewBeforeSubmit=true`.
- Manual review-before-submit remains the active learner recording flow.

## 3. What Changed in ReaDirect-AI-ASR

Files changed:

- `READIRECT_ASR_AUTOMATIC_MODE_CONTRACT.md`

Code changes needed:

- None for Prompt 2.

Endpoint contract status:

- `POST /analyze-audio` is already sufficient for future automatic mode when called through Laravel's existing audio upload and ASR orchestration.
- No response fields were removed, renamed, or faked.
- GOP remains conditional on runtime model availability.
- WPM/WCPM were not added to ASR; Laravel currently computes WPM/WCPM where needed.

Response fields available for Prompt 3 include:

- `transcript`
- `raw_transcript`
- `corrected_transcript`
- `displayed_transcript`
- `expected_text`
- `accepted`
- `is_correct`
- `is_accepted`
- `confidence`
- `confidence_level`
- `raw_wer`
- `corrected_wer`
- `raw_cer`
- `corrected_cer`
- `audio_quality`
- `pause_metrics`
- `retry_required`
- `uncertain`
- `learner_retry_message`
- `error_type`
- `feedback_hint`
- `target_phoneme`
- GOP fields when available
- phoneme fields when available
- `processing_seconds`
- `warnings`
- `error`

Bad-audio outcomes documented:

- too short
- mostly silent
- low volume
- clipped
- no speech detected
- quality gate failed
- retry required
- uncertain

## 4. What Changed in ReaDirect-IA

Files changed:

- `ciel_agent/schemas.py`
- `tests/test_api.py`
- `READIRECT_IA_AUTOMATIC_MODE_CONTRACT.md`

Endpoint contract status:

- `POST /ia/ciel/decide` remains the current Ciel decision endpoint.
- It still receives already-scored attempt evidence and returns a constrained `ciel_agent` payload.
- Ciel still cannot alter official scoring, mastery, placement, or progression.

Optional schema fields added:

- `listening_mode`
- `session_mode`
- `automatic_session_id`
- `current_agent_state`
- `silence_timeout`
- `chunk_id`

These fields are optional and are not used by the deterministic engine yet. They do not change current Ciel decisions.

Animation safety confirmation:

- Allowed Ciel animation labels remain:
  - `c-advise`
  - `c-clap`
  - `c-confused`
  - `c-congrats`
  - `c-happy`
  - `c-idle`
  - `c-talk`
  - `c-thinking-1`
  - `c-thinking-2`
  - `c-thinking-3`
- No new animation names were added.
- `c-congrats` remains restricted to `final_assessment_completion`.
- `official_progression_changed` remains false by contract.

## 5. Current Future-Ready Architecture

Intended Prompt 3 flow:

```text
Laravel module page
-> future automatic recorder session
-> /learner/audio/upload
-> AIAnalysisResolver
-> ReaDirect-AI-ASR /analyze-audio
-> Laravel official scoring/progression
-> CielTutorAgentClient
-> ReaDirect-IA /ia/ciel/decide
-> Laravel frontend renders Ciel action
```

Important ownership boundaries:

- Laravel remains the only official scoring/progression authority.
- ASR remains speech-analysis only.
- IA remains Ciel decision-only.
- The future automatic recorder should be a mode-specific layer beside the current manual recorder, not a replacement for it.

## 6. Prompt 3 TODO List

Prompt 3 should implement:

- Learner dashboard toggle UI.
- Persistence route/controller for `listening_mode`.
- Automatic mode UI in supported module/activity pages.
- Live silence detection/VAD.
- Automatic chunk recording.
- Auto-submit after speech ends.
- Pause listening while Ciel speaks.
- Resume listening after TTS ends.
- Route cleanup for supported/unsupported pages.
- Duplicate chunk prevention.
- Focus mode integration.
- Full manual mode regression tests.

Idempotency plan for Prompt 3:

- Automatic mode should generate a client-side `automatic_session_id`.
- Each submitted chunk should include a `chunk_id`.
- Laravel should store or cache processed chunk ids per learner/session.
- Laravel should reject duplicate automatic chunk submissions.
- Manual mode should not require chunk ids and should remain unaffected.

## 7. Safety Rules for Prompt 3

- Manual mode remains default.
- No listening without an explicit learner click.
- No global listening.
- No listening while Ciel speaks.
- No duplicate submissions.
- No unsupported animations.
- `c-congrats` only for final assessment completion.
- Laravel remains scoring source of truth.
- ASR remains speech-analysis only.
- IA remains Ciel decision-only.
- Automatic mode must not change diagnostic/module/final scoring rules.
- Automatic mode must not change placement/mastery thresholds.
- Automatic mode must not replace the existing manual recorder.

## 8. Tests Run

Laravel:

- `php -l app\Services\LearnerListeningModeService.php` - passed.
- `php -l app\Models\LearnerPreference.php` - passed.
- `php -l app\Http\Controllers\Learner\LearnerDashboardController.php` - passed.
- `php artisan test --filter=LearnerListeningModeServiceTest` - passed, 2 tests / 6 assertions.
- `php artisan test` - passed, 235 tests / 1831 assertions.
- `npm run build` - passed. Vite reported an existing chunk-size warning for large generated JS, but the build completed successfully.

ReaDirect-IA:

- `python -m unittest tests.test_api tests.test_ciel_agent -v` - passed, 15 tests.
- `python -m pytest` - passed, 15 tests.

ReaDirect-AI-ASR:

- `python -m pytest` - ran 246 tests; 244 passed and 2 failed.
- Failing existing assertions:
  - `tests/test_api.py::test_health_endpoint`: expected `body["status"] == "ok"`, actual response status was `healthy`.
  - `tests/test_dynamic_expected_word_correction.py::test_dynamic_safety_rejects_weak_or_short_function_word_matches`: expected `correct_expected_word("pulled", "poled", "passage", ...)["accepted"] is False`, actual was `True`.
- Prompt 2 only added ASR documentation and did not change ASR code, endpoint behavior, correction behavior, or health behavior.

## 9. Developer Review Before Prompt 3

Review these files before implementing automatic listening:

- `app/Services/LearnerListeningModeService.php`
- `database/migrations/2026_06_17_000001_create_learner_preferences_table.php`
- `resources/js/Composables/useAutomaticCielListeningSession.js`
- `ReaDirect-AI-ASR/READIRECT_ASR_AUTOMATIC_MODE_CONTRACT.md`
- `ReaDirect-IA/ciel_agent/schemas.py`
- `ReaDirect-IA/READIRECT_IA_AUTOMATIC_MODE_CONTRACT.md`

The main implementation risk for Prompt 3 is not preference storage; it is coordinating automatic microphone state with Ciel speaking/TTS, duplicate submission prevention, and preserving manual-mode regression behavior.
