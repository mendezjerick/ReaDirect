# ReaDirect Existing System Audit

Date inspected: 2026-06-17

Scope: existing code and assets only. This document records the current baseline for the main Laravel ReaDirect repository, ReaDirect-AI-ASR, ReaDirect-IA, and ReaDirect-TTS. No production behavior was changed for this audit.

Post-Prompt 3 note: Toggleable Automatic Ciel Listening Mode has since been implemented in the Laravel repo for supported module activity and mastery pages. This audit remains the pre-implementation baseline. See `docs/READIRECT_AUTOMATIC_CIEL_LISTENING_MODE.md` for the current automatic mode implementation details.

## 1. Repository Roles

### Main Laravel ReaDirect

Current role: the main learner web application and the source of truth for official learner progression, scoring, attempt state, module assignment, mastery decisions, dashboard state, local audio storage, ASR orchestration, Ciel frontend display, visual agent rendering, and TTS proxy/cache.

Important inspected files and folders:

- `routes/web.php`: Defines learner access, dashboard, diagnostic, module, final assessment, audio upload, agent voice, and IA asset routes.
- `app/Http/Controllers/Learner/LearnerDashboardController.php`: Builds dashboard props from the current learner, flow state, attempts, modules, rewards, and latest assessment results.
- `app/Services/LearnerFlowService.php`: Resolves learner stage, primary dashboard action, active diagnostic/final/module attempts, and resume routes.
- `app/Http/Controllers/Learner/DiagnosticAssessmentController.php`: Owns diagnostic assessment task flow, submission, scoring calls, passage/comprehension scoring, and module placement handoff.
- `app/Http/Controllers/Learner/FinalAssessmentController.php`: Owns final reassessment setup, task flow, scoring, passage/comprehension handling, completion status, and comparison summary.
- `app/Http/Controllers/Learner/ModuleController.php`, `ModuleActivityController.php`, `ModuleMasteryController.php`: Own module start, overview, practice activities, per-item checks, mastery checks, and mastery result progression.
- `app/Services/ModuleActivitySelectionService.php`: Locks practice and mastery items for module attempts.
- `app/Services/ModuleItemRetryService.php`: Handles module item checking, retry counts, ASR resolution, Ciel local cue generation, IA Ciel decision calls, response persistence, and retry state.
- `app/Http/Controllers/Learner/AudioUploadController.php`: Accepts learner audio uploads, stores audio files, calls ASR/STT analysis, persists assessment progress for assessment/passage uploads, and returns transcription payloads.
- `app/Services/AudioStorageService.php`: Validates and stores learner audio files and metadata.
- `app/Services/AI/ReadirectAIService.php`: HTTP client for the ReaDirect-AI-ASR FastAPI service.
- `app/Services/AI/AIAnalysisResolver.php`: Chooses manual transcript, AI ASR, or fallback STT and normalizes resolved transcript data.
- `app/Services/ASR/AsrResponseNormalizer.php`: Defines Laravel transcript priority, completion checks, audio-quality failure checks, and ASR metadata normalization.
- `app/Services/CrlaScoringService.php`, `ReadingComprehensionScoringService.php`, `SentenceReadingScoringService.php`, `ModuleScoringService.php`, `ModuleMasteryService.php`, `ModulePlacementService.php`, `DiagnosticPlacementService.php`: Current official scoring and placement logic.
- `app/Agents/Ciel/*`, `app/Services/Ciel/CielTutorAgentClient.php`, `app/Services/CielFocusModeService.php`: Current Ciel local rules, dialogue lookup, IA service client, deterministic fallback, focus-mode event, and reward/star handling.
- `app/Services/TTS/AgentTtsService.php`, `app/Http/Controllers/AgentVoiceController.php`: Laravel proxy/cache for local TTS service output.
- `resources/js/Components/Learner/AudioRecorder.vue`: Current browser recorder.
- `resources/js/Pages/Learner/*` and `resources/js/Pages/Learner/Modules/*`: Learner diagnostic, final assessment, module, dashboard, completion, summary, and placement UI.
- `resources/js/Components/Learner/AgentSpeakerPanel.vue`, `resources/js/Components/Learner/CielFocusMode.vue`, `resources/js/Components/Agents/AgentVideoPlayer.vue`, `resources/js/utils/agentMedia.js`, `resources/js/utils/agentInteraction.js`, `resources/js/utils/agentMediaPreloader.js`: Current visual agent, animation, and agent interaction mapping.
- `database/migrations/*`, `app/Models/*`: Current persistence model for learners, attempts, modules, responses, audio, rewards, reinforcement, settings, LLM interaction records, and audit/report tables.
- `public/ia-assets`: Current public asset mount/junction to ReaDirect-IA assets.

### ReaDirect-AI-ASR

Current role: local FastAPI speech recognition and speech-analysis service. It receives expected-centric audio or text analysis requests from Laravel, runs audio validation/quality checks, runs a Wav2Vec2 ASR provider or mock provider, normalizes/corrects transcripts around the expected text, computes WER/CER and phoneme/GOP metadata when available, returns retry/uncertainty decisions, exposes content metadata/adaptive recommendation helpers, and supports supervised correction memory.

Important inspected files and folders:

- `api/main.py`: FastAPI app and endpoint definitions for health, version, analysis, content lookup, recommendations, and reinforcement correction.
- `api/schemas.py`: Request and response contracts for all ASR service endpoints.
- `api/service.py`: Main analysis pipeline for text/audio requests, expected-context resolution, audio quality handling, ASR invocation, transcript normalization, GOP/dynamic correction application, reading-analysis response building, and retry/quality-gate responses.
- `api/dependencies.py`: Runtime configuration from environment variables, including ASR provider/model paths, transcript normalization, GOP, dynamic correction, and audio-quality thresholds.
- `api/security.py`: Optional `X-ReaDirect-AI-Token` validation when API auth is enabled.
- `src/readirect_asr/audio/preprocessing.py`: Audio validation, duration reading, 16 kHz mono loading, RMS/volume, clipping, speech segment, silence ratio, and pause metrics.
- `src/readirect_asr/asr/provider_factory.py`: Chooses Wav2Vec2-only provider or mock provider.
- `src/readirect_asr/asr/wav2vec2_asr.py`: Local Wav2Vec2 ASR loading, decoding, phoneme evidence extraction, and decoder metadata.
- `src/readirect_asr/text/transcript_normalizer.py`: Expected-centric transcript normalization and WER/CER metadata.
- `src/readirect_asr/correction/dynamic_expected_word_correction.py`: Dynamic expected-word correction, fragment checks, ASR spelling variant handling, alignment repair, and reinforcement matching.
- `src/readirect_asr/pronunciation/gop.py`: GOP/phoneme scoring from frame evidence when a phoneme model and alignment are available.
- `src/readirect_asr/scoring/reading_analyzer.py`: Heuristic answer matching, phoneme comparison, error type, skill signal, feedback hint, and recommended action.
- `src/readirect_asr/evaluation/asr_metrics.py`: WER/CER helpers.
- `src/readirect_asr/adaptive/*`, `src/readirect_asr/content/*`: Content lookup/enrichment and adaptive recommendation helpers.

### ReaDirect-IA

Current role: source of truth for visual agent media assets and a lightweight deterministic Miss Ciel tutor-agent FastAPI service. It does not own official scoring, placement, mastery, or progression. It receives already-scored attempt evidence and returns a constrained `ciel_agent` decision payload.

Important inspected files and folders:

- `main.py`: FastAPI app exposing `/health`, `/ia/ciel/status`, and `/ia/ciel/decide`.
- `ciel_agent/schemas.py`: Ciel request/response contracts, allowed modes, exact animation labels, final-congrats validation, and status schema.
- `ciel_agent/engine.py`: Ciel perceive/decide/act/update flow and deterministic decision branching.
- `ciel_agent/rules.py`: Error normalization, low-confidence detection, known confusion keys, and memory-key derivation.
- `ciel_agent/memory.py`: JSON file memory backend at `data/ciel_memory.json` by default.
- `ciel_agent/templates.py`: Approved deterministic learner-facing Ciel messages.
- `definitions/ciel.yaml`, `policies/ciel-coach.yaml`, `dialogue/ciel.yaml`, `schemas/*.json`, `scenarios/ciel-coaching.json`: Agent definitions, policy/specification, dialogue, schemas, and fixtures. Runtime use of some YAML/spec files from Laravel is partial; Needs verification.
- `manifests/media.json`: Ciel media manifest.
- `assets/images/*` and `assets/videos/*`: Current Vivian, Ciel, and Estelle images/videos used by the Laravel frontend through `/ia-assets`.

### ReaDirect-TTS

Current role: local FastAPI Kokoro TTS service for short agent voice lines. Laravel calls this service, caches the returned WAV bytes in Laravel local storage, and serves cached audio through Laravel routes. Browser pages do not load audio directly from the TTS service in the current Laravel path.

Important inspected files:

- `tts_service.py`: FastAPI app, agent voice profiles, text sanitization, cache key generation, Kokoro generation, and `/health`, `/voices`, `/synthesize` endpoints.
- `README.md`: Current local setup, selected voices, service URL, and endpoint documentation.
- Main Laravel `app/Services/TTS/AgentTtsService.php`: Calls `/synthesize`, caches WAVs under `tts_cache`, returns fallback text payload when disabled/unavailable, and reports dashboard status.
- Main Laravel `app/Http/Controllers/AgentVoiceController.php`: Exposes `/agent-voice/synthesize` JSON and `/agent-voice/{cacheKey}` WAV responses.

## 2. Current Learner Flow

### Learner dashboard

The dashboard is rendered by `LearnerDashboardController` and `resources/js/Pages/Learner/Dashboard.vue`. The controller resolves the current learner, asks `LearnerFlowService` for the current flow state, loads active modules, latest completed diagnostic attempt, latest final reassessment attempt, and star rewards from `CielFocusModeService`.

The dashboard's primary action comes from `flowState.primary_action_label` and `flowState.primary_action_route`. Module cards show the learner's current module as available and other modules as locked. Diagnostic/final/module result summaries use fields already stored on attempts.

### Diagnostic assessment

The diagnostic flow is in `DiagnosticAssessmentController` and learner Vue pages under `resources/js/Pages/Learner`.

Current sequence:

1. Start diagnostic: `storeStart()` creates an `assessment_attempts` row with `attempt_type=diagnostic`, selects 10 Task 1 letters, stores the attempt id in session, and sets learner stage to diagnostic in progress.
2. Task 1 letter pronunciation: 10 selected letters are submitted. Laravel scores accepted answers and stores `task_1_score`. If score is 7 or higher, Task 2A is auto-scored as 10 and skipped; otherwise Task 2A is required.
3. Task 2A rhyming words: 10 selected rhyme prompts are scored when required.
4. Task 2B word in sentence: 10 selected sentence/target-word prompts are analyzed with sentence reading metrics and summarized into `task_2b_score`.
5. CRLA summary: total CRLA score and classification are shown before the reading passage.
6. Passage reading: one reading passage is selected. Audio is uploaded, ASR can be used, incorrect word count is computed from transcript/word alignment or fallback text comparison, and `reading_accuracy` is stored.
7. Comprehension: five responses are exact-matched against accepted answers. Comprehension percent and final reading score are computed and stored.
8. Module placement: `DiagnosticPlacementService` assigns module placement or grade-ready state and completes the diagnostic attempt.

`LearnerFlowService` enforces resume routes based on missing scores: Task 1, Task 2A if needed, Task 2B, passage, comprehension, then module placement.

### Module flow

The module flow is in `ModuleController`, `ModuleActivityController`, `ModuleMasteryController`, `ModuleActivitySelectionService`, `ModuleItemRetryService`, and Vue pages under `resources/js/Pages/Learner/Modules`.

Current sequence:

1. Dashboard or module index starts/resumes the current module.
2. `ModuleActivitySelectionService` creates/resumes a `module_attempts` row and locks selected practice/mastery items in `module_attempt_items`.
3. Practice activities are shown one activity type at a time. The learner checks individual items through the module `check` route.
4. `ModuleItemRetryService` resolves the submitted answer/audio, scores the item, appends attempt history, increments retry count, marks the item resolved when correct or after 3 tries, generates Ciel feedback/cues, and stores a `module_activity_responses` row.
5. The practice `store` route requires all current activity items to be resolved before moving to the next activity or mastery.
6. The mastery check uses locked mastery items, the same per-item check path, and then `ModuleMasteryService` decides whether to repeat, move forward, move back, or enter final reassessment pending.

Module progression thresholds are currently:

- Module 1: mastery score >= 90 moves to Module 2; otherwise repeat Module 1.
- Module 2: >= 90 moves to Module 3; 60-89 repeats Module 2; < 60 returns to Module 1.
- Module 3: >= 90 moves to final reassessment pending; 70-89 repeats Module 3; < 70 returns to Module 2.

### Final assessment

The final reassessment is implemented in `FinalAssessmentController` and pages under `resources/js/Pages/Learner/FinalAssessment`.

It is available when learner stage is `final_reassessment_pending`. A final attempt is created with `attempt_type=final_reassessment` and `baseline_assessment_attempt_id` pointing to the completed diagnostic. The final assessment clones baseline diagnostic items by task where applicable and then runs Task 1, Task 2A if needed, Task 2B, passage reading, and comprehension.

On final comprehension submit, Laravel marks the final attempt `final_reassessment_completed`, computes comparison summary through `FinalAssessmentComparisonService`, updates the learner stage, and redirects to learner completion. A `FinalAssessment/Summary.vue` page exists, but the current `summary` route redirects to the learner completion page. Needs verification if that Vue page is active in another path.

### How learners start activities

- Dashboard primary action is resolved by `LearnerFlowService`.
- Diagnostic starts through `/learner/diagnostic/start`.
- Modules start through `/learner/modules/{module}/start`.
- Practice activities start through `/learner/modules/{module}/activity/{activityType}` after `ModuleActivitySelectionService` locks/resumes an attempt.
- Mastery starts through `/learner/modules/{module}/mastery`.
- Final reassessment starts through `/final-assessment/start`.

### How answers are submitted

- Diagnostic and final task pages submit structured response arrays to controller store methods. Voice tasks can upload audio first through `/learner/audio/upload`, then submit response rows containing `audio_file_id`, transcripts, and accepted/displayed transcript data.
- Passage reading submits audio or an uploaded audio id. Manual fallback is present for QA/sandbox paths only.
- Comprehension submits selected/written responses and is scored by accepted-answer matching.
- Module practice/mastery checks submit each item through check endpoints. The item is resolved after a correct response or 3 attempts. The final store endpoint verifies all items are resolved before progression.

### How feedback is shown

- Assessment pages show agent panels, transcription states, retry messages, summaries, and scoring results from controller props and audio upload responses.
- Module pages show per-item feedback from `ModuleItemRetryService`, including `feedback_text`, `agent_cue`, `ciel_agent`, retry state, and Ciel focus events.
- `CielFocusMode.vue` displays modal teaching/star-reward events from `CielFocusModeService`.
- `AgentSpeakerPanel.vue` can synthesize spoken agent text through `/agent-voice/synthesize`.

### How attempts/progress are tracked

- Learner stage and current module are stored on `learners.current_stage` and `learners.current_module_id`.
- Diagnostic/final assessment state, score fields, classification fields, placement, baseline comparison, status, and completion timestamps are stored on `assessment_attempts`.
- Selected assessment items are locked in `assessment_attempt_items`.
- Assessment task responses are stored in `assessment_task_responses`.
- Module attempt state is stored in `module_attempts`.
- Locked module items are stored in `module_attempt_items`.
- Module response attempts, retry count, feedback, transcripts, and scoring metadata are stored in `module_activity_responses`.
- Audio is stored in `audio_files` and linked back to responses where applicable.
- Ciel star rewards are stored in `learner_rewards`.

## 3. Current Recording Flow

Current recorder implementation: `resources/js/Components/Learner/AudioRecorder.vue`.

Current browser APIs:

- Uses `navigator.mediaDevices.getUserMedia({ audio: true })`.
- Uses `MediaRecorder`, preferring `audio/webm` when supported.
- Uses Web Audio API (`AudioContext`/`OfflineAudioContext`) after recording to decode, analyze speech frames, trim/pad, resample to 16 kHz, and emit a WAV file.

Current start/stop behavior:

- Start is blocked when disabled, submitting, already submitted, or already recording.
- Starting clears previous local recording state, stops agent audio, requests microphone permission, creates `MediaRecorder`, and begins recording.
- A cue delay defaults to 1400 ms. UI says "Wait for cue" before allowing "Speak now".
- Stop is blocked until `minDurationSeconds` is reached.
- A timer updates visible duration and auto-stops at `maxDurationSeconds`.
- After stop, the component converts the recording to WAV, validates speech duration and post-cue silence metrics, then enters saved/retry/error state.
- The default UI requires learner review before submit. `autoTranscribeOnStop` exists as a prop, but `AssessmentModeService::props()` currently forces `autoTranscribeOnStop=false` and `requireReviewBeforeSubmit=true`.

Where audio is sent:

- `AudioRecorder.vue` emits a `File` to parent pages.
- Parent learner pages build `FormData` and POST to Laravel `/learner/audio/upload`.
- `AudioUploadController` stores the file and calls `AIAnalysisResolver`, which calls ReaDirect-AI-ASR when enabled or fallback STT/manual paths when configured.

How UI handles recording state:

- Internal status values include `ready`, `recording`, `processing`, `saved`, `retry`, and `error`.
- UI labels include `Ready`, `I'm listening`, `Processing`, `Listen`, `Submitted`, `Retry`, and `Needs permission`.
- Buttons and parent pages use `submitting`, `submitted`, `disabled`, uploading/checking flags, and form processing state to prevent obvious duplicate clicks.

Silence detection, auto-stop, and VAD status:

- Post-recording speech/silence validation exists in the recorder. It estimates RMS speech frames, speech duration, leading/trailing silence, silence ratio, and validates post-cue leading silence and silence ratio.
- The recorder trims/pads/resamples after recording.
- Server-side ASR audio quality checks also detect silence, low volume, clipping, duration, speech segments, and pauses.
- No live voice activity detection loop, automatic listening mode, automatic start-on-speech, or live silence auto-stop was found in the current learner recorder.
- Max-duration auto-stop exists.

Duplicate submission status:

- Client-side duplicate prevention exists through disabled/submitting/submitted states and the recorder refusing submit without a current file.
- Server persistence often uses `updateOrCreate` for assessment progress/response rows and module response keys.
- No explicit idempotency token or global duplicate-submission lock was found for `/learner/audio/upload`. Confirmed missing.

## 4. Current ASR / Speech Analysis Flow

### API endpoints

From ReaDirect-AI-ASR `api/main.py`:

- `GET /health`
- `GET /version`
- `POST /analyze-text`
- `POST /analyze-audio`
- `POST /reinforcement/corrections`
- `POST /content-item`
- `POST /analyze-content-item`
- `POST /recommend-next`

Authentication: `api/security.py` accepts `X-ReaDirect-AI-Token` only when `API_AUTH_ENABLED` is true. Laravel sends this header when `READIRECT_AI_API_TOKEN` is configured.

### Input request format

`AnalyzeAudioRequest` fields:

```text
audio_path, expected_text, accepted_answers, prompt_id, prompt_type, module_key,
activity_type, task_type, learner_response_id, attempt_id, content_metadata,
learner_history, candidate_items, debug, developer_reinforcement_enabled,
developer_user_role, developer_user_id
```

Laravel's `AIAnalysisResolver` currently sends these payload fields for audio/text analysis:

```text
expected_text, prompt_type, accepted_answers, prompt_id, module_key, module_type,
activity_type, assessment_type, task_type, item_id, learner_id, attempt_id,
current_scoring_context, learner_history, candidate_items, content_metadata, debug,
audio_path for audio, actual_text for text
```

Fields such as `module_type`, `assessment_type`, `item_id`, `learner_id`, and `current_scoring_context` are not declared in the ASR service Pydantic request model. They are ignored by the service unless added to accepted schema fields later. Needs verification if any downstream code depends on those ignored fields.

`AnalyzeTextRequest` fields:

```text
expected_text, actual_text, accepted_answers, prompt_id, module_key,
activity_type, task_type, content_metadata, learner_history, candidate_items, debug
```

`ContentItemRequest` fields:

```text
prompt_id, expected_text, module_key, activity_type, task_type
```

`RecommendNextRequest` fields:

```text
learner_history, current_context, candidate_items, module_key, activity_type, top_k, debug
```

`ReinforcementCorrectionRequest` fields:

```text
expected_text, raw_transcript, prompt_type, accepted, retry_required, uncertain,
correction_strategy_used, created_by, source, notes, supervised_reinforcement_enabled,
developer_reinforcement_enabled, developer_user_role
```

### Audio preprocessing and quality checks

`src/readirect_asr/audio/preprocessing.py` currently:

- Allows `.wav`, `.mp3`, `.m4a`, `.webm`, `.ogg`, and `.flac`.
- Reads duration with `soundfile` and falls back to `librosa`.
- Loads audio as mono at 16 kHz for quality analysis.
- Computes RMS, dBFS, low-volume flag, peak amplitude, clipped sample ratio, clipped flag, speech segments, speech ratio, silence ratio, speech duration, pause count, long/very-long pause count, total pause seconds, longest pause, and pause ratio.
- Produces quality flags for `too_short`, `mostly_silent`, `low_volume`, `clipped`, and `no_speech_detected`.
- Emits warnings such as `audio_too_short`, `audio_too_long`, `no_speech_detected`, `mostly_silent`, `low_volume`, and `clipped`.
- Can quality-gate before ASR when strict quality gate and retry-on-bad-quality are enabled.

### Transcript generation

`provider_factory.py` chooses `Wav2Vec2OnlyASR` for providers `wav2vec2_only`, `wav2vec2`, or `hf_wav2vec2_local`; otherwise it uses `MockASR`.

`wav2vec2_asr.py` currently:

- Loads local Wav2Vec2 ASR model and processor.
- Uses configured decode mode, beam settings, optional language model path, hotwords, alpha, beta, and fallback options.
- Loads audio at the model sampling rate.
- Decodes transcript and normalizes it.
- Returns raw decoded transcript, normalized transcript, model route/family/model path, duration, sample rate, inference timing, decoder metadata, and phoneme evidence when available.
- Exposes `phoneme_frame_evidence()` for GOP alignment.

Whisper fields are still present in the response contract for compatibility, but `whisper_removed` is true and health/version report Whisper removed/unavailable.

### Expected-centric correction

Expected-centric correction is present.

The audio path in `api/service.py`:

1. Resolves expected text and accepted answers from request and content repository metadata.
2. Runs ASR.
3. Runs `normalize_asr_transcript(...)`.
4. Computes GOP metadata when possible.
5. Applies `apply_dynamic_expected_word_correction(...)`.
6. Builds `AnalysisResponse`.

Dynamic correction supports exact normalized match, letter aliases, reinforcement error-transcript matching, phoneme/GOP-supported expected matches, suspicious-fragment checks, ASR spelling variants, and dynamic alignment repair according to the inspected code. Correction is skipped when `retry_required` or `uncertain` is true and the corresponding skip settings are enabled.

### Confidence handling

Current confidence handling includes:

- ASR provider `confidence` when available.
- Normalization fields such as `confidence_level`, `threshold_used`, and `confidence_or_threshold_used`.
- Similarity fields such as `character_similarity`, `token_similarity`, `phonetic_similarity_score`, `composite_score`, and `phoneme_similarity`.
- GOP fields such as `gop_score`, `overall_gop_score`, `gop_confidence`, and `acoustic_confidence` when GOP is available.
- Uncertainty decision fields: `uncertain`, `retry_required`, `uncertainty_reasons`, `quality_gate_failed`, `learner_retry_message`, and `developer_quality_notes`.

### WER / CER / WPM / WCPM

ASR returns:

- `raw_wer`
- `corrected_wer`
- `raw_cer`
- `corrected_cer`

No ASR `AnalysisResponse` field for WPM or WCPM was found. WPM/WCPM are computed in Laravel's `SentenceReadingScoringService` for sentence/module assessment scoring when duration and word correctness data are available.

### Phoneme / GOP / pronunciation analysis

Phoneme and GOP analysis are present but conditional.

Present code:

- Wav2Vec2 ASR can extract observed phonemes and acoustic frame evidence through a separate phoneme model.
- `gop.py` computes expected phonemes from CMU/fallback/letter maps, maps them to model vocabulary labels, uses CTC forced alignment, scores aligned phonemes, returns weak/lowest phonemes, word scores, decoded phonemes, acoustic confidence, and decision labels.
- If GOP is disabled, expected text is missing, prompt type is unsupported, audio quality is bad, phoneme evidence is unavailable, or model vocabulary/alignment fails, GOP returns a not-available/unsupported payload instead of scores.
- Health reports `gop_status` as `Off`, `Ready`, or `Failed` based on configuration and phoneme model availability.

Therefore GOP/phoneme analysis is implemented in code but not guaranteed to be available in every runtime. Needs verification in the target runtime model paths.

### Exact `AnalysisResponse` fields currently returned

From `api/schemas.py`:

```text
ok
request_id
mode
provider
model_size
prompt_id
expected_text
accepted_answers
transcript
normalized_transcript
raw_transcript
corrected_transcript
displayed_transcript
prompt_type
asr_route
model_family
model_used
wav2vec2_transcript
whisper_transcript
whisper_removed
raw_wer
corrected_wer
raw_cer
corrected_cer
phonetic_similarity_score
composite_score
accepted
normalization_applied
normalization_reason
correction_strategy_used
accepted_by_letter_alias
accepted_by_phonetic_threshold
accepted_by_known_confusion
accepted_by_letter_lattice
accepted_by_letter_normalization
accepted_by_exact_match
accepted_by_vowel_tail
accepted_by_phoneme_evidence
gop_enabled
gop_available
gop_supported
gop_score
overall_gop_score
gop_confidence
acoustic_confidence
gop_decision
gop_threshold
gop_prompt_type
gop_expected_phonemes
canonical_phonemes
canonical_expected_phonemes
gop_observed_phonemes
decoded_phonemes
decoded_acoustic_phonemes
gop_phoneme_scores
phoneme_scores
gop_word_scores
mispronounced_phonemes
weak_words
weak_phoneme
weak_phoneme_score
lowest_phoneme
lowest_phoneme_score
nearest_confusion
alignment_quality
gop_model_version
gop_model_path
gop_frame_count
gop_duration_seconds
gop_fallback_used
gop_correction_applied
gop_error
dynamic_correction_enabled
dynamic_correction_applied
dynamic_correction_strategy
dynamic_correction_sub_strategy
dynamic_correction_confidence
dynamic_correction_threshold
dynamic_spelling_similarity
dynamic_phoneme_similarity
dynamic_gop_score
dynamic_homophone_match
dynamic_context_score
dynamic_correction_reason
dynamic_suspicious_fragment
dynamic_fragment_reasons
dynamic_phoneme_coverage
asr_spelling_variant_enabled
asr_spelling_variant_applied
asr_spelling_variant_strategy
asr_spelling_variant_sub_strategy
asr_spelling_variant_confidence
asr_spelling_variant_threshold
consonant_skeleton_similarity
vowel_tolerant_similarity
expected_phoneme_coverage
variant_edit_similarity
variant_reason
word_alignment
accepted_by_reinforcement_match
reinforcement_source_file
reinforcement_expected_label
reinforcement_matched_transcript
reinforcement_match_normalized
reinforcement_match_original
critical_phoneme
critical_phoneme_detected
critical_phoneme_expected_position
critical_phoneme_reason
critical_pair_detected
confidence_level
threshold_used
confidence_or_threshold_used
confidence
is_correct
is_exact
is_accepted
character_similarity
token_similarity
similarity_label
expected_phonemes
expected_phoneme_source
expected_phoneme_variants
observed_phonemes
actual_phonemes
phoneme_similarity
error_type
error_position
feedback_hint
coach_hint_key
learner_safe_summary
skill_signal
target_phoneme
target_position
recommended_practice_focus
recommended_action
adaptive_recommendation
learner_summary
audio_quality
pause_metrics
uncertain
retry_required
uncertainty_reasons
quality_gate_failed
learner_retry_message
developer_quality_notes
content_metadata
enrichment_metadata
analysis_source
debug_metadata
developer_reinforcement_mode
reinforcement_saved
reinforcement_duplicate
reinforcement_target_file
reinforcement_reason
warnings
debug_info
processing_seconds
error
asr_model_name
decode_mode
beam_search
language_model_used
decoder_backend
```

## 5. Current Scoring and Assessment Logic

### Source of truth

Laravel is the source of truth for official scoring, placement, mastery, completion, and learner progression.

The ASR service provides transcripts, accepted/correction signals, pronunciation/quality metrics, WER/CER, word alignment, uncertainty/retry decisions, and feedback-hint metadata. It does not own official diagnostic/module/final scores.

### What Laravel handles

Laravel currently handles:

- Diagnostic attempt creation, item locking, scoring, CRLA routing, reading accuracy, comprehension scoring, placement, learner stage updates, and response persistence.
- Final reassessment attempt creation, baseline item cloning, scoring, passage/comprehension scoring, comparison summary, learner stage updates, and completion.
- Module practice/mastery item selection, retries, correctness, feedback persistence, mastery score, module progression, stage updates, and rewards.
- Official answer matching through `AnswerMatchingService` and task-specific scoring services.
- Official transcript selection for scoring and display through `AsrResponseNormalizer` and controller/service code.

### What ASR handles

ASR currently handles:

- Expected-centric audio/text analysis.
- Raw and corrected/displayed transcript generation.
- WER/CER and similarity metadata.
- Audio quality, pause, uncertainty, and retry decisions.
- Dynamic correction and accepted flags.
- GOP/phoneme metadata when available.
- Heuristic error type, skill signal, and feedback hint metadata.
- Adaptive recommendation helper responses when called.

### Diagnostic rules currently present

CRLA:

- Task 1 contains 10 letter items.
- Task 1 score >= 7 skips Task 2A by assigning Task 2A score 10.
- Task 1 score <= 6 requires Task 2A.
- Task 2B contains 10 word-in-sentence items.
- CRLA total = Task 1 + Task 2A + Task 2B.
- CRLA classification:
  - 0-10: Full Refresher
  - 11-16: Moderate Refresher
  - 17-26: Light Refresher
  - 27-30: Grade Ready

Reading:

- Incorrect word count is calculated from expected passage, transcript, and word alignment when available.
- Reading accuracy is `max(0, 100 - incorrect_words * 2)`.
- Comprehension is exact accepted-answer matching over 5 items.
- Final reading score = 60% comprehension + 40% reading accuracy.
- Reading classification:
  - <= 25: Low Emerging
  - <= 50: High Emerging
  - <= 75: Developing
  - <= 90: Transitioning
  - > 90: Grade Level

Placement:

- CRLA Full/Moderate/Light -> Module 1.
- CRLA Grade Ready + Low/High Emerging reading -> Module 2.
- CRLA Grade Ready + Developing/Transitioning reading -> Module 3.
- CRLA Grade Ready + Grade Level reading -> no module, grade ready.

### Module/final assessment rules currently present

Module:

- Item correctness uses accepted-answer matching and ASR accepted flags for short prompts where applicable.
- Practice/mastery responses resolve when correct or after 3 attempts.
- Mastery score is earned points / possible points * 100.
- Module progression thresholds are listed in section 2.

Final:

- Final reassessment uses baseline diagnostic-selected items where applicable.
- Task 1/2A/2B flow mirrors diagnostic routing.
- Final passage and comprehension are scored through the same core reading/comprehension services.
- Final completion stores comparison summary and marks the learner final reassessment completed.

### Stored transcript/score fields

Current storage found:

- `audio_files`: uploaded audio path, file metadata, duration, recording context, transcript, STT confidence/error/completion, AI provider/model/request id, AI transcript, normalized transcript, confidence, warnings, and AI completion timestamp.
- `assessment_task_responses`: learner id, attempt item id, task type/key, expected answer, `learner_transcript` for scoring transcript, `response_text` for displayed transcript, selected answer where applicable, `transcript_source`, `stt_confidence`, correctness, score, rule applied, metadata, `metadata_json`, audio file link, agent commentary fields, and AI response fields.
- `module_activity_responses`: module attempt item link, learner answer, expected answer, feedback text, retry count, mastery flag, error type, metadata, audio file link, transcript source, learner transcript, STT confidence, agent commentary, and AI response fields.
- `assessment_attempts`: task scores, CRLA total/classification, reading accuracy, incorrect words, comprehension count/score, final reading score/classification, assigned module/placement, baseline assessment id, comparison summary, status, and completed timestamp.
- `module_attempts`: status, scores, current activity/progress fields, module progression metadata. Exact current column set should be verified against all migrations before schema migration work. Needs verification.

## 6. Current Ciel / Intelligent Agent Logic

### Current implementation style

Ciel is currently deterministic and rule-based/state-based. There is no runtime LLM dependency in the inspected IA service.

There are two active layers:

- Laravel local Ciel logic: `CielCoachDecisionService`, `CielDialogueCatalog`, `CielFocusModeService`, and `CielTutorAgentClient` fallback decisions.
- Optional ReaDirect-IA service: `CielTutorAgentClient` posts already-scored evidence to `/ia/ciel/decide` when `readirect.ciel.service_enabled` is true. It accepts only valid Ciel decisions and falls back locally if unavailable/invalid.

### Existing Ciel states/modes

ReaDirect-IA allowed modes:

```text
idle, instruction, listening, checking, correct_praise, soft_retry, hint,
focus_teach, slow_practice, final_encouragement, final_assessment_completion
```

Laravel frontend maps Ciel actions to:

```text
idle, talk, thinking, thinking_1, thinking_2, thinking_3, happy,
confused, advise, clap, congrats
```

### Existing Ciel triggers

Current module/check triggers include:

- Correct response -> praise/clap.
- Low confidence, retry-required, uncertain, blank transcript, too-short audio, or low ASR confidence -> thinking/hint/retry-recording.
- Word deletion/insertion/boundary errors -> slow practice or focus teach depending attempt count.
- Repeated incorrect attempts (attempt >= 2) -> focus teach, lock interaction, repeat after agent, blank-screen focus mode.
- Vowel/final sound/letter confusion -> targeted deterministic teaching message.
- Correct streak and section completion in Laravel local policy can trigger positive/clap behavior.
- `CielFocusModeService` creates a teaching focus event on the second wrong module attempt and a star reward every 3-correct streak.
- Final assessment completion mode exists in IA schema and fallback. The module item retry path does not set `is_final_assessment_completion`.

### Existing feedback, retry, teaching, and focus behavior

- `ModuleItemRetryService` stores feedback text, retry state, agent cue, Ciel agent decision, and Ciel focus event.
- `CielTutorAgentClient` returns `lock_interaction`, `repeat_after_agent`, `teaching_focus`, `focus_mode`, `reason_codes`, and `official_progression_changed=false`.
- `CielFocusMode.vue` displays multi-step Ciel focus events with TTS and agent media.
- `CielFocusModeService` awards stars through `LearnerReward`.

### How Ciel receives information

Laravel sends this IA payload shape from `CielTutorAgentClient`:

```text
learner_id, session_id, module_type, expected, transcript, is_correct, attempt,
asr_confidence, gop_score, phoneme_errors, error_type, target_phoneme,
activity_id, is_final_assessment_completion, audio_duration_seconds,
audio_too_short, retry_required, uncertain
```

The IA service accepts those fields as `AttemptContext`, ignores unknown extras, derives perception, increments JSON memory for error keys, and returns a constrained `ciel_agent`.

### How Ciel decides what to say/do

ReaDirect-IA `engine.py` decides in this priority order:

1. Final completion -> `final_assessment_completion`, `c-congrats`.
2. Correct -> `correct_praise`, `c-clap`.
3. Low confidence -> `hint`, `c-thinking-1`.
4. Word deletion/insertion/boundary -> `slow_practice` on first attempt or `focus_teach` on later attempts, `c-advise`.
5. Attempt >= 2 -> `focus_teach`, `c-advise`.
6. First retry -> `soft_retry`, `c-advise` for vowel/final sound cases or `c-confused` otherwise.

Laravel fallback mirrors the same broad behavior. Ciel cannot alter official progression; returned payloads enforce `official_progression_changed=false`.

## 7. Current Visual Agent and Animation System

### Where each agent is used

- Miss Vivian: assessment/diagnostic/final-assessment task guidance through `agent-type="assessment"` and context mapping in `agentInteraction.js`.
- Miss Ciel: module practice, module overview/index, module mastery checks, Ciel focus mode, and module coaching through `agent-type="coach_feedback"` and module/practice/mastery context mapping.
- Miss Estelle: routing, results, summaries, placement, recommendations, module mastery result, and completion/result presentation through evaluator contexts.

Confirmed code locations include `AgentSpeakerPanel.vue`, `AgentVideoPlayer.vue`, `agentMedia.js`, `agentInteraction.js`, learner diagnostic/final pages, module pages, `Completion.vue`, and `docs/agent-assets.md`.

### Available animation files confirmed in assets

From `ReaDirect-IA/assets` and the `/ia-assets` mount:

Ciel:

```text
assets/images/Ciel/Ciel.png
assets/videos/Ciel/c-advise.mp4
assets/videos/Ciel/c-clap.mp4
assets/videos/Ciel/c-confused.mp4
assets/videos/Ciel/c-congrats.mp4
assets/videos/Ciel/c-happy.mp4
assets/videos/Ciel/c-idle.mp4
assets/videos/Ciel/c-talk.mp4
assets/videos/Ciel/c-thinking-1.mp4
assets/videos/Ciel/c-thinking-2.mp4
assets/videos/Ciel/c-thinking-3.mp4
```

Vivian:

```text
assets/images/Vivian/Vivian.png
assets/videos/Vivian/v-congrats.mp4
assets/videos/Vivian/v-idle.mp4
assets/videos/Vivian/v-talk.mp4
assets/videos/Vivian/v-think.mp4
```

Estelle:

```text
assets/images/Estelle/Estelle.png
assets/videos/Estelle/e-congrats.mp4
assets/videos/Estelle/e-idle.mp4
assets/videos/Estelle/e-results-1.mp4
assets/videos/Estelle/e-results-2.mp4
assets/videos/Estelle/e-talk.mp4
```

### Animation mapping in code

`resources/js/utils/agentMedia.js` maps:

- Ciel idle: `videos/Ciel/c-idle.mp4`; fallback: `images/Ciel/Ciel.png`.
- Ciel actions: `thinking`, `thinking_1`, `thinking_2`, `thinking_3`, `talk`, `happy`, `confused`, `advise`, `clap`, `congrats`.
- Vivian idle: `videos/Vivian/v-idle.mp4`; fallback: `images/Vivian/Vivian.png`.
- Vivian actions: `talk`, `thinking`, `congrats`.
- Estelle idle: `videos/Estelle/e-idle.mp4`; fallback: `images/Estelle/Estelle.png`.
- Estelle actions: `talk`, `results`, `congrats`.

`agentInteraction.js` maps contexts:

- `assessment`, `diagnostic`, `final_assessment` -> Vivian.
- `module`, `practice`, `mastery` -> Ciel.
- `results`, `routing`, `summary`, `recommendation`, `placement` -> Estelle.

### Idle/talking/thinking/confused/congrats behavior

- `AgentVideoPlayer.vue` keeps idle media visible and overlays one interaction video when requested.
- Idle videos loop.
- Interaction videos play once unless `loopInteraction` is explicitly true.
- PNG fallbacks are used when idle video fails or is missing.
- `AgentSpeakerPanel.vue` maps TTS speaking to `talk` for passive states.
- Congrats action is blocked unless `allowCongrats`/`congratsAllowed` is true. Without this, Estelle falls back to `results`, and other agents fall back to `idle`.
- `c-congrats.mp4` exists. It is used by `Completion.vue`, which shows all three agents with `action="congrats"` and `allow-congrats`. IA and Laravel Ciel decision validators also reserve `c-congrats` for `final_assessment_completion`; normal module attempts do not trigger it.

### No-queue/no-interrupt logic

`AgentVideoPlayer.vue` tracks `isBusy`. New cues are ignored while an interaction is preparing or playing, except a non-interaction idle cue can reset the player when `loopInteraction` is active. There is no interaction queue.

## 8. Existing API Contracts

### Laravel -> ReaDirect-AI-ASR

Client: `app/Services/AI/ReadirectAIService.php`.

Configured endpoints in `config/readirect_ai.php`:

```text
health -> /health
version -> /version
analyze_audio -> /analyze-audio
analyze_text -> /analyze-text
recommend_next -> /recommend-next
content_item -> /content-item
reinforcement_correction -> /reinforcement/corrections
```

Laravel sends JSON and optional `X-ReaDirect-AI-Token`.

Typical audio analysis payload from `AIAnalysisResolver`:

```json
{
  "expected_text": "cat",
  "prompt_type": "word",
  "accepted_answers": ["cat"],
  "prompt_id": "module-item-id",
  "module_key": "module_2",
  "module_type": "module_2",
  "activity_type": "word_reading",
  "assessment_type": "module_activity",
  "task_type": "word_reading",
  "item_id": 123,
  "learner_id": 45,
  "attempt_id": 67,
  "current_scoring_context": {},
  "learner_history": [],
  "candidate_items": [],
  "content_metadata": {},
  "debug": false,
  "audio_path": "absolute/local/path/to/audio.wav"
}
```

The exact declared ASR request schema is narrower than this Laravel payload; extra fields are ignored by the current Pydantic model.

### ReaDirect-AI-ASR -> Laravel

ASR returns `AnalysisResponse` as listed in section 4. Laravel normalizes transcript priority as:

- Scoring transcript: `corrected_transcript -> transcript -> raw_transcript -> normalized_transcript -> fallback`.
- Learner display transcript: `displayed_transcript -> corrected_transcript -> transcript -> raw_transcript -> normalized_transcript`.
- Debug transcript: `raw_transcript`.

Laravel `/learner/audio/upload` returns learner-facing base JSON:

```json
{
  "audio_file_id": 1,
  "audio_file_public_id": "public-id",
  "mime_type": "audio/wav",
  "duration_seconds": 1.25,
  "transcription_status": "transcribed",
  "transcription_message": "Transcription complete.",
  "message": "Transcription complete.",
  "transcript": "cat",
  "displayed_transcript": "cat",
  "can_submit": true,
  "retry_required": false,
  "learner_retry_message": null,
  "transcript_source": "ai_asr",
  "word_alignment": []
}
```

When raw AI payload visibility is allowed, Laravel appends many debug fields such as raw/corrected transcript, ASR route/model, WER/CER, phonetic/composite scores, accepted flags, quality fields, dynamic correction fields, word alignment, STT confidence/error, and AI warnings.

### Laravel -> ReaDirect-IA

Client: `app/Services/Ciel/CielTutorAgentClient.php`.

Endpoint:

```text
POST {readirect.ciel.base_url}{readirect.ciel.decide_endpoint}
Default: http://127.0.0.1:8003/ia/ciel/decide
```

Request payload:

```json
{
  "learner_id": 45,
  "session_id": "module-session",
  "module_type": "module_practice",
  "expected": "cat",
  "transcript": "cap",
  "is_correct": false,
  "attempt": 2,
  "asr_confidence": null,
  "gop_score": 0.72,
  "phoneme_errors": [],
  "error_type": "final_sound_error",
  "target_phoneme": "T",
  "activity_id": 123,
  "is_final_assessment_completion": false,
  "audio_duration_seconds": 1.25,
  "audio_too_short": false,
  "retry_required": false,
  "uncertain": false
}
```

### ReaDirect-IA -> Laravel

Response shape:

```json
{
  "ciel_agent": {
    "agent": "ciel",
    "mode": "focus_teach",
    "animation": "c-advise",
    "emotion": "gentle_correction",
    "message": "Let's practice CAT. Listen carefully. CAT.",
    "display_target": "CAT",
    "next_action": "listen_then_retry",
    "lock_interaction": true,
    "repeat_after_agent": true,
    "teaching_focus": "final_sound_missing",
    "focus_mode": {
      "enabled": true,
      "layout": "blank_screen",
      "target_position": "center",
      "agent_position": "bottom",
      "target_size": "large"
    },
    "memory_update": {
      "error_key": "FINAL_SOUND_MISSING",
      "count_increment": 1,
      "current_count": 1,
      "learner_id": "45",
      "session_id": "module-session"
    },
    "reason_codes": ["FINAL_SOUND_MISSING"],
    "official_progression_changed": false,
    "decision_trace": ["perceive", "decide", "act", "observe_update"]
  }
}
```

Laravel accepts the IA decision only if `agent=ciel`, animation is one of the known Ciel animation names, and `c-congrats` is only used with `mode=final_assessment_completion`. Otherwise it falls back to deterministic Laravel Ciel behavior.

### Laravel -> ReaDirect-TTS

Client: `app/Services/TTS/AgentTtsService.php`.

Laravel endpoint exposed to browser:

- `POST /agent-voice/synthesize`: returns JSON with `audio_url` or text fallback.
- `GET /agent-voice/{cacheKey}`: serves cached `audio/wav`.

TTS service endpoint:

```text
POST http://127.0.0.1:8002/synthesize
```

Request payload:

```json
{
  "agent": "miss_ciel",
  "text": "Good try. Please try once more.",
  "voice": null,
  "speed": 1.0,
  "cache": true
}
```

TTS response is `audio/wav` bytes with headers including provider, agent, voice, and cache key. Laravel stores those bytes locally and returns a Laravel audio URL.

## 9. Existing Database / Models / State

Confirmed tables/models related to the requested areas:

- Learners: `learners` model/table stores learner identity, class/school links, current stage, current module, learner code/public id, and active state.
- Activities/content/modules: `learning_contents`, `modules`, `module_activities`, `module_attempts`, `module_attempt_items`.
- Assessments: `assessment_attempts`, `assessment_attempt_items`, `assessment_task_responses`.
- Module responses/feedback: `module_activity_responses` stores learner answers, expected answers, retry count, feedback text, transcript/source/confidence fields, AI fields, and metadata.
- Transcripts/audio: `audio_files` stores uploaded file paths/metadata, transcript, STT fields, AI fields, recording context, and response links.
- Scores/progress: score/status fields are on `assessment_attempts`, `module_attempts`, `assessment_task_responses`, and `module_activity_responses`; current stage/current module are on `learners`.
- Feedback/commentary: agent commentary fields exist on response tables. Module feedback text is stored on `module_activity_responses`.
- Recommendations: `recommendations` stores diagnostic placement/recommendation output.
- Agent profiles: `agent_profiles` exists and is seeded for agent identities. Current runtime Ciel/agent decisions are primarily service/payload driven, not stored as durable agent state except response metadata/rewards.
- Agent state/rewards: `learner_rewards` stores star rewards; ReaDirect-IA also has JSON session memory in `data/ciel_memory.json`.
- Learner settings/preferences: `system_settings` exists for system-level settings. No learner-specific automatic-listening preference table/field was found. Confirmed missing.
- Reinforcement: `asr_supervised_reinforcement_cases` stores supervised ASR reinforcement cases and service responses.
- LLM/debug/admin: `llm_prompt_templates`, `llm_interactions`, `audit_logs`, and `reports` exist. Runtime Ciel IA service currently reports `llm_enabled=false`.

Important migrations inspected/listed include:

- `2026_04_24_080000_create_assessment_attempt_items_table.php`
- `2026_04_24_100000_create_module_attempt_items_table.php`
- `2026_04_24_101000_add_phase_three_module_fields.php`
- `2026_04_24_120000_add_phase_six_audio_fields.php`
- `2026_04_24_130000_add_phase_seven_llm_interaction_fields.php`
- `2026_04_24_140000_add_agent_commentary_fields_to_responses.php`
- `2026_04_25_100000_add_final_reassessment_fields_to_assessment_attempts.php`
- `2026_05_07_000000_create_system_settings_table.php`
- `2026_05_08_000000_normalize_learner_stages.php`
- `2026_05_20_000000_create_asr_supervised_reinforcement_cases_table.php`
- `2026_06_08_000001_create_learner_rewards_table.php`

## 10. Current Strengths and Stable Parts

Stable flows and architecture worth preserving:

- Laravel is clearly the official scoring/progression authority, while ASR provides analysis signals.
- Learner flow is centralized in `LearnerFlowService`, reducing route/resume ambiguity.
- Diagnostic, module, and final assessment flows are already separated by controllers and services.
- Assessment and module item locking creates stable attempt reproducibility.
- Transcript handling distinguishes scoring transcript, displayed transcript, raw/debug transcript, source, confidence, and ASR metadata.
- The recorder already has robust manual review, WAV conversion, speech-duration validation, trim/pad/resample, and parent-driven submission.
- ASR service has a broad response contract with quality, uncertainty, transcript normalization, WER/CER, dynamic correction, and conditional GOP fields.
- Module retry behavior is explicit: correct or max 3 attempts.
- Ciel cannot alter official progression; IA output is validated and fallback-safe.
- Visual agent media mapping is centralized in `agentMedia.js` and constrained by exact asset files.
- Agent interaction player has sensible no-queue/no-interrupt behavior and PNG fallbacks.
- TTS is isolated behind Laravel proxy/cache and has text fallback when disabled or unavailable.
- ReaDirect-IA remains the asset source of truth, with Laravel using `/ia-assets` rather than committing media copies.

Code that should not be touched unless necessary:

- Official scoring services and placement/mastery thresholds.
- `LearnerFlowService` route/stage resolution.
- `AudioRecorder.vue` manual review and conversion behavior.
- `AIAnalysisResolver` and `AsrResponseNormalizer` transcript priority rules.
- Ciel validation/fallback rules in `CielTutorAgentClient`.
- Agent media registry and no-queue behavior in `AgentVideoPlayer.vue`.
- Existing database fields for attempts/responses/audio, unless a future feature explicitly requires schema changes.

## 11. Gaps or Unknowns

### Confirmed missing

- No automatic Ciel listening mode was found.
- No dashboard toggle for automatic listening was found.
- No learner-specific automatic-listening setting/preference field was found.
- No live VAD/auto-start/auto-stop-on-silence loop was found in the learner recorder.
- No ASR `AnalysisResponse` WPM/WCPM fields were found.
- No explicit idempotency token was found for learner audio upload.

### Partially present

- `AudioRecorder.vue` has an `autoTranscribeOnStop` prop, but Laravel currently forces `autoTranscribeOnStop=false` and `requireReviewBeforeSubmit=true`.
- Silence/speech checks exist after recording and on the ASR service, but not as automatic live listening.
- GOP/phoneme analysis is implemented but depends on local model paths, phoneme frame evidence, prompt type, and audio quality.
- Ciel has a `listening` mode in IA schema and frontend maps `listening` as an idle/passive label, but no automatic background listening behavior was found.
- ReaDirect-IA YAML specs and policies exist; runtime use is primarily through Python Ciel engine and Laravel client validation. Needs verification.

### Needs verification

- Whether `resources/js/Pages/Learner/FinalAssessment/Summary.vue` is reachable in production, because the current final summary route redirects to learner completion.
- Whether the hard-coded `/ia-graphics/{filename}` path in `routes/web.php` is still used; current visual agent code uses `/ia-assets`.
- Runtime GOP readiness on the deployment machine, including actual Wav2Vec2 ASR model, phoneme model, language model, and decoder backend availability.
- Whether any admin/sandbox paths intentionally rely on extra Laravel fields that the ASR Pydantic request model currently ignores.
- Full production values for `READIRECT_AI_ENABLED`, `CIEL_AGENT_ENABLED`, `TTS_ENABLED`, model paths, and token auth.

## 12. Future Context Only: Automatic Ciel Listening Mode

This section is context only. No implementation or preparation was made.

Likely future integration points based on current code:

- Current recorder: `resources/js/Components/Learner/AudioRecorder.vue`.
- Recording parent pages: diagnostic/final task pages and `resources/js/Pages/Learner/Modules/ModuleActivity.vue`, `ModuleMasteryCheck.vue`.
- Audio upload endpoint: `app/Http/Controllers/Learner/AudioUploadController.php`, route `POST /learner/audio/upload`.
- ASR client and transcript completion rules: `app/Services/AI/AIAnalysisResolver.php`, `app/Services/AI/ReadirectAIService.php`, `app/Services/ASR/AsrResponseNormalizer.php`.
- ASR service endpoint: ReaDirect-AI-ASR `POST /analyze-audio`.
- Current Ciel local policy: `app/Agents/Ciel/CielCoachDecisionService.php`, `app/Agents/Ciel/CielDialogueCatalog.php`, `app/Services/CielFocusModeService.php`.
- Current IA service: ReaDirect-IA `main.py`, `ciel_agent/schemas.py`, `ciel_agent/engine.py`, `ciel_agent/rules.py`.
- Current Ciel agent client: `app/Services/Ciel/CielTutorAgentClient.php`.
- Current feedback display: `resources/js/Components/Learner/CielFocusMode.vue`, `resources/js/Components/Learner/AgentSpeakerPanel.vue`, module activity/mastery Vue pages.
- Current visual agent mapping/player: `resources/js/utils/agentMedia.js`, `resources/js/utils/agentInteraction.js`, `resources/js/Components/Agents/AgentVideoPlayer.vue`.
- Current stage/dashboard state: `app/Services/LearnerFlowService.php`, `resources/js/Pages/Learner/Dashboard.vue`.

Any future automatic listening mode should be treated as a new feature layered onto these existing components, with Laravel scoring/progression rules preserved as the baseline.
