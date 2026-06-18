# ReaDirect Automatic Ciel Listening Mode

Date implemented: 2026-06-17

## 1. What Was Implemented

Toggleable Automatic Ciel Listening Mode was implemented for supported Laravel module activity and module mastery pages. Manual Recording Mode remains the default and the existing review-before-submit recorder remains available and unchanged for manual mode.

Automatic mode:

- Starts only after the learner clicks `Start Reading with Ciel`.
- Requests microphone permission only after that click.
- Uses browser `MediaRecorder` plus Web Audio API RMS volume monitoring.
- Records finalized speech chunks only, not continuous streaming.
- Uploads chunks through the existing Laravel audio upload path.
- Reuses existing Laravel ASR, scoring, retry, progression, Ciel IA, and focus-mode flow.
- Pauses while Ciel/TTS/focus teaching is active.
- Stops on learner stop, unmount/route leave, completion/final submit, error, or dashboard return.

## 2. Repo Ownership

Laravel owns the feature UI, preference persistence, microphone session, duplicate chunk guard, audio upload orchestration, official scoring/progression, Ciel rendering, IA client call, and TTS pause/resume hooks.

ReaDirect-AI-ASR was not changed. It remains speech-analysis only through `POST /analyze-audio`.

ReaDirect-IA was not changed in Prompt 3 code. It already accepts optional automatic context fields and remains Ciel decision-only.

## 3. Enable/Disable Flow

Learners enable the mode on the learner dashboard in `Recording Mode`.

Options:

- `Manual Recording Mode`: stable default.
- `Automatic Ciel Listening Mode`: optional.

The setting is saved through `PATCH /learner/listening-mode` and stored in `learner_preferences.listening_mode`.

## 4. Default Mode

Default remains `manual`.

Existing learners without a `learner_preferences` row resolve to `manual`. Invalid values are rejected by validation and normalized to manual by `LearnerListeningModeService` when read.

## 5. Supported Pages

Automatic mode is rendered only on:

- `resources/js/Pages/Learner/Modules/ModuleActivity.vue`
- `resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue`

These pages already use Miss Ciel as the module coach.

## 6. Unsupported Pages

Automatic mode is not enabled on:

- Dashboard itself
- Diagnostic assessment
- Final assessment
- Results pages
- Login/access pages
- Teacher pages
- Admin pages
- Module overview pages

## 7. State Machine

State constants live in `resources/js/Composables/useAutomaticCielListeningSession.js`:

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

## 8. Microphone Lifecycle

The microphone lifecycle is managed by `useAutomaticCielListeningSession.js`:

- No microphone access on page mount.
- `startSession()` calls `navigator.mediaDevices.getUserMedia`.
- The stream is connected to an `AudioContext` analyser.
- `MediaRecorder` starts only after RMS speech threshold is crossed.
- Active tracks are stopped by `stopSession()`.
- The session is stopped on component unmount and before final activity/mastery submission.

## 9. Silence Detection Thresholds

Default thresholds:

- `speechThreshold`: `0.035`
- `silenceThreshold`: `0.018`
- `minimumSpeechDurationMs`: `650`
- `silenceDurationBeforeSubmitMs`: `1000`
- `maximumChunkDurationMs`: `10000`
- `cooldownAfterSubmitMs`: `700`
- `resumeAfterRetryMs`: `1100`

These are conservative RMS thresholds and may need tuning after classroom/browser QA.

## 10. Audio Chunk Submission Flow

Automatic chunk flow:

```text
AutomaticCielListeningPanel
-> useAutomaticCielListeningSession
-> /learner/audio/upload
-> AIAnalysisResolver
-> ReaDirect-AI-ASR /analyze-audio
-> existing module check endpoint
-> ModuleItemRetryService
-> Laravel official scoring/retry/progression
-> CielTutorAgentClient
-> ReaDirect-IA /ia/ciel/decide
-> module page renders Ciel/focus response
```

Manual mode still uses the existing `AudioRecorder.vue` review-before-submit flow.

## 11. Duplicate Chunk Prevention

Automatic mode generates:

- `automatic_session_id`
- `chunk_id`

Frontend duplicate prevention uses an in-session `Set` of submitted chunk ids.

Backend duplicate prevention uses `app/Services/AutomaticListeningChunkGuard.php`, backed by Laravel cache with a 7200-second TTL. It applies only when `listening_mode=automatic_ciel`; manual mode does not require chunk ids.

Duplicate backend submissions return HTTP 409 with `duplicate_chunk=true`.

## 12. Laravel -> ASR Flow

No ASR endpoint changes were made. Laravel continues to call ASR through:

- `AudioUploadController`
- `AudioStorageService`
- `AIAnalysisResolver`
- `ReadirectAIService`
- `AsrResponseNormalizer`

ASR remains speech-analysis only.

## 13. Laravel -> IA Flow

Laravel still calls IA through `app/Services/Ciel/CielTutorAgentClient.php`.

For automatic mode, Laravel now forwards optional context fields when present:

- `listening_mode`
- `session_mode`
- `automatic_session_id`
- `current_agent_state`
- `silence_timeout`
- `chunk_id`

IA still receives already-scored attempt evidence and cannot change official scoring or progression.

## 14. Ciel Pause/Resume Rules

Automatic listening pauses when:

- Agent TTS starts in `AgentSpeakerPanel.vue`.
- Ciel focus-mode TTS starts in `CielFocusMode.vue`.
- Focus mode is visible.
- A chunk is uploading/checking.
- The current item is resolved.
- The learner stops the session.

Listening resumes only when:

- The session is still active.
- The current page is a supported module page.
- The current item is unresolved.
- Ciel/TTS/focus mode is no longer active.
- Upload/checking is not active.

## 15. Focus Mode Integration

Existing focus mode remains the source of truth:

- `resources/js/Components/Learner/CielFocusMode.vue`
- `app/Services/CielFocusModeService.php`
- `ModuleItemRetryService`

Automatic mode pauses during focus teaching and resumes after focus mode closes only if retry is still allowed and the item is unresolved.

## 16. Error Handling

Handled client-side outcomes include:

- Permission denied
- No microphone found
- Microphone busy
- Browser unsupported
- Too-short chunk
- Upload/ASR failure
- Retry-required or unusable ASR result
- IA/TTS fallback through existing Laravel behavior
- Route leave/unmount cleanup
- Duplicate chunk rejection

When automatic mode fails, the page shows a child-friendly message and offers `Use Manual Recording Mode` as a page-level fallback.

## 17. Files Changed

Laravel:

- `routes/web.php`
- `app/Http/Controllers/Learner/LearnerListeningModeController.php`
- `app/Http/Controllers/Learner/ModuleActivityController.php`
- `app/Http/Controllers/Learner/ModuleMasteryController.php`
- `app/Services/LearnerListeningModeService.php`
- `app/Services/AutomaticListeningChunkGuard.php`
- `app/Services/ModuleItemRetryService.php`
- `app/Services/Ciel/CielTutorAgentClient.php`
- `resources/js/Pages/Learner/Dashboard.vue`
- `resources/js/Pages/Learner/Modules/ModuleActivity.vue`
- `resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue`
- `resources/js/Components/Learner/AutomaticCielListeningPanel.vue`
- `resources/js/Components/Learner/AgentSpeakerPanel.vue`
- `resources/js/Components/Learner/CielFocusMode.vue`
- `resources/js/Composables/useAutomaticCielListeningSession.js`
- `tests/Feature/LearnerListeningModePreferenceTest.php`
- `tests/Unit/AutomaticListeningChunkGuardTest.php`
- `tests/Unit/CielTutorAgentClientTest.php`
- `tests/Unit/AgentSpeakerPanelStructureTest.php`
- `docs/READIRECT_EXISTING_SYSTEM_AUDIT.md`
- `docs/READIRECT_AUTOMATIC_LISTENING_READINESS.md`
- `docs/READIRECT_AUTOMATIC_CIEL_LISTENING_MODE.md`

ReaDirect-AI-ASR:

- `READIRECT_ASR_AUTOMATIC_MODE_CONTRACT.md` documentation note only.

ReaDirect-IA:

- `READIRECT_IA_AUTOMATIC_MODE_CONTRACT.md` documentation note only.

## 18. Environment Variables

No new environment variables were added.

Existing ASR, IA, and TTS configuration remains unchanged.

## 19. Test Commands Run

Laravel:

- `php -l app\Http\Controllers\Learner\LearnerListeningModeController.php; php -l app\Services\AutomaticListeningChunkGuard.php; php -l app\Http\Controllers\Learner\ModuleActivityController.php; php -l app\Http\Controllers\Learner\ModuleMasteryController.php; php -l app\Services\ModuleItemRetryService.php; php -l app\Services\Ciel\CielTutorAgentClient.php`: all passed.
- `php artisan test tests\Feature\LearnerListeningModePreferenceTest.php tests\Unit\AutomaticListeningChunkGuardTest.php tests\Unit\CielTutorAgentClientTest.php tests\Unit\AgentSpeakerPanelStructureTest.php`: 17 passed.
- `npm run build`: passed with the existing large chunk warning.
- `php artisan test`: 240 passed.

ReaDirect-IA:

- `python -m pytest`: 15 passed.

ReaDirect-AI-ASR:

- `python -m pytest`: 244 passed, 2 failed.
- Existing failures remained:
  - `tests/test_api.py::test_health_endpoint`: expected `status == "ok"`, actual `healthy`.
  - `tests/test_dynamic_expected_word_correction.py::test_dynamic_safety_rejects_weak_or_short_function_word_matches`: `pulled`/`poled` accepted unexpectedly.

## 20. Known Limitations

- Browser microphone QA was not run in this environment.
- RMS-based silence detection is intentionally lightweight and may need tuning after real device testing.
- Automatic mode uses finalized browser audio chunks; it does not stream ASR.
- Automatic mode is scoped to module activity/mastery pages only.
- If a learner changes the dashboard preference in another tab, an already-open module page will not update until navigation/refresh.
- ASR test suite has two pre-existing unrelated failures listed above.

## 21. Rollback Instructions

Safe rollback path:

- Set affected learners back to `manual` in `learner_preferences`.
- Revert the Laravel files listed in section 17.
- Remove the dashboard route/controller for `/learner/listening-mode` if rolling back the toggle.
- Remove `AutomaticListeningChunkGuard` and automatic metadata validation from module check controllers.
- Rebuild assets with `npm run build`.
- Run `php artisan test`.

Manual Recording Mode is isolated behind the existing `AudioRecorder.vue` branch, so rolling back automatic mode should not require recorder logic changes.
