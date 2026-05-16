# ASR Pipeline State And Resume Audit

## Root Problems Found

- Mixed string/integer item IDs were compared with raw strict array equality in diagnostic, final assessment, module activity, and module mastery submissions. FormData can submit IDs as strings, which made valid submissions look stale.
- Audio upload progress saves could set `answered_at` before the scored submit path ran. A page could then reload as if an item was done even though it was only transcribed.
- ASR retry, uncertainty, and audio-quality flags were returned but not used consistently as completion gates.
- Vue pages selected transcript fields independently. Most used displayed/corrected/transcript/raw order, but modules skipped corrected/raw fallback.
- The recorder enforced total elapsed recording time only. Pre-speech silence could satisfy the minimum even when useful speech was too short.

## Files Inspected

- `resources/js/Components/Learner/AudioRecorder.vue`
- `resources/js/Pages/Learner/Task1LetterPronunciation.vue`
- `resources/js/Pages/Learner/Task2ARhymingWords.vue`
- `resources/js/Pages/Learner/Task2BWordInSentence.vue`
- `resources/js/Pages/Learner/PassageReading.vue`
- `resources/js/Pages/Learner/FinalAssessment/Task1LetterPronunciation.vue`
- `resources/js/Pages/Learner/FinalAssessment/Task2ARhymingWords.vue`
- `resources/js/Pages/Learner/FinalAssessment/Task2BWordInSentence.vue`
- `resources/js/Pages/Learner/FinalAssessment/PassageReading.vue`
- `resources/js/Pages/Learner/Modules/ModuleActivity.vue`
- `resources/js/Pages/Learner/Modules/ModuleMasteryCheck.vue`
- `app/Http/Controllers/Learner/AudioUploadController.php`
- `app/Http/Controllers/Learner/DiagnosticAssessmentController.php`
- `app/Http/Controllers/FinalAssessmentController.php`
- `app/Http/Controllers/Learner/ModuleActivityController.php`
- `app/Http/Controllers/Learner/ModuleMasteryController.php`
- `app/Services/AI/AIAnalysisResolver.php`
- `app/Services/LearnerFlowService.php`

## Voice Activity Pages Found

- Diagnostic letter pronunciation
- Diagnostic rhyming words
- Diagnostic word in sentence / Task 2B
- Diagnostic passage reading
- Final assessment letter pronunciation
- Final assessment rhyming words
- Final assessment word in sentence / Task 2B
- Final assessment passage reading
- Module activity
- Module mastery check

## Shared ASR Pipeline Rules

- Recording must pass useful-speech checks before upload.
- Upload/ASR may save audio and transcript metadata.
- `answered_at` is only set by scored submit handlers after a usable transcript or scorable ASR result is accepted.
- `retry_required`, `uncertain`, and failed audio quality block completion.
- Wrong but usable transcripts remain valid attempts and are scored incorrect.

## Transcript Selection Rules

- Scoring transcript: `corrected_transcript`, then `transcript`, then `raw_transcript`.
- Display transcript: `displayed_transcript`, then `corrected_transcript`, then `transcript`, then `raw_transcript`.
- Debug transcript preserves `raw_transcript` when available.

## Item Completion Rules

An item can complete only when the backend has a usable transcript/scorable result and ASR does not require retry. Empty, silence/noise-only, retry-required, uncertain, and failed-quality results remain unfinished unless manual developer QA fallback explicitly supplies a valid transcript.

## Recorder Timing Rules

- The shared recorder respects the cue delay.
- It estimates speech duration separately from total duration.
- It records metadata for total duration, speech duration, leading silence, trailing silence, silence ratio, speech ratio, and trim status.
- It trims leading/trailing silence before upload when decoding is available.
- Defaults: letters 0.25s speech, words/rhymes 0.35s, sentences 0.75s, passages/paragraphs 1.5s.

## Sentence/Passage Fluency Exception

The shared capture/upload/transcript contract is the same. Sentence/passage flows still run existing fluency/alignment scoring after transcript capture through `SentenceReadingScoringService` and passage reading scoring.

## Fixes Made

- Added `App\Services\ASR\AsrResponseNormalizer` for shared transcript selection and completion gating.
- Added `App\Support\SubmittedItemSet` for normalized ID comparison.
- Updated diagnostic, final, module activity, and mastery controllers to use normalized ID comparison.
- Updated scored submit handlers to reject retry/uncertain/bad-audio ASR results before scoring or setting `answered_at`.
- Updated audio upload progress save so it no longer marks assessment items answered.
- Added shared frontend `resources/js/utils/asrResponse.js`.
- Updated all learner voice pages to use shared frontend ASR response handling and pass recorder metadata.
- Updated `AudioRecorder.vue` to validate useful speech duration and silence ratios.

## Tests Added

- `tests/Unit/SubmittedItemSetTest.php`
- `tests/Unit/AsrResponseNormalizerTest.php`
- Added retry-required upload coverage in `tests/Feature/AudioRecordingTest.php`.

## Remaining Risks

- Browser-side speech detection uses RMS heuristics. It is intentionally conservative and may need tuning after real classroom recordings.
- Existing module sentence activities route through generic module scoring; only existing assessment sentence scoring computes detailed fluency metrics today.
- Passage UI still computes learner-visible diff locally for display, while backend remains the source of persisted progress.
