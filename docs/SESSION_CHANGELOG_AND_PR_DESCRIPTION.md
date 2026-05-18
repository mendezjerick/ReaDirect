# Session Changelog and Pull Request Notes

Date: May 17, 2026

Current local branch at time of writing: `main`

Suggested branch name:

```bash
feature/diagnostic-polish-and-dashboard-pages
```

## Summary

This session focused on polishing the learner diagnostic flow, fixing audio transcription edge cases, improving CRLA and reading result screens, removing unused notification menus, and adding real learner dashboard pages for Progress, Rewards, and Help.

The biggest functional fix was for Task 1 letter transcription, especially the letter `Z`. The biggest UI work was making diagnostic pages match the provided ReaDirect reference designs more closely while keeping the existing character images, such as Miss Estelle and Miss Vivian.

## Diagnostic Flow Changes

### Shared diagnostic progress stepper

Several diagnostic pages were updated to show the same step position and progress state across the diagnostic journey:

- Intro
- Warm-Up
- Task 1
- Task 2A
- Task 2B
- Sentence Reading

The shared step data now lives in:

- `resources/js/utils/diagnosticSteps.js`

Pages updated to use the shared diagnostic step behavior include:

- `resources/js/Pages/Learner/DiagnosticIntro.vue`
- `resources/js/Pages/Learner/DiagnosticStart.vue`
- `resources/js/Pages/Learner/Task1LetterPronunciation.vue`
- `resources/js/Pages/Learner/TaskOneLetterPronunciation.vue`
- `resources/js/Pages/Learner/Task2ARhymingWords.vue`
- `resources/js/Pages/Learner/Task2ASummary.vue`
- `resources/js/Pages/Learner/Task2BWordInSentence.vue`
- `resources/js/Pages/Learner/TaskRoutingResult.vue`
- `resources/js/Pages/Learner/ReadingIntro.vue`
- `resources/js/Pages/Learner/PassageReading.vue`
- `resources/js/Pages/Learner/ComprehensionQuestions.vue`
- `resources/js/Pages/Learner/CrlaSummary.vue`
- `resources/js/Pages/Learner/ReadingSummary.vue`
- `resources/js/Pages/Learner/ModulePlacementResult.vue`

### Task 1 letter pronunciation

The Task 1 page was redesigned to better match the diagnostic reference layout:

- larger centered prompt card for the target letter
- stronger diagnostic progress bar
- left-side guide character panel
- clearer recording card and transcript area
- cleaner "Next" state after recording/submission

The page affected most directly:

- `resources/js/Pages/Learner/Task1LetterPronunciation.vue`

### Task 1 `Z` transcription fix

Task 1 had a bug where short recordings for isolated letters, especially `Z`, could produce a usable transcript but still be blocked as unreliable.

Before the fix, examples like `they`, `v`, `w`, or other short ASR outputs could be blocked with:

```text
Please record again. The audio was not reliable enough to score.
```

The backend now allows Task 1 letter prompts to complete when a usable transcript exists, even if the ASR marks the short audio as uncertain. This does not auto-pass the answer. It only lets the attempt submit so scoring can mark it correct or incorrect.

Files changed:

- `app/Services/ASR/AsrResponseNormalizer.php`
- `tests/Unit/AsrResponseNormalizerTest.php`
- `docs/TASK1_Z_TRANSCRIPTION_FIX.md`

Verification performed:

```bash
php artisan test tests\Unit\AsrResponseNormalizerTest.php
```

### Task 2B word-in-sentence page

Task 2B was redesigned to match the provided reference more closely:

- cleaner left character panel while keeping Miss Vivian
- before-recording and after-recording states
- clearer target sentence card with highlighted word
- recording controls and transcript card aligned with the reference design
- removed the decorative star on the right side of the sentence card when requested
- improved state handling around submitted/recorded answers

Main file:

- `resources/js/Pages/Learner/Task2BWordInSentence.vue`

Related notes:

- `docs/TASK2B_TRANSCRIPTION_AUDIT.md`
- `docs/TASK2B_STATE_AND_RESUME_BUG.md`

### Task 2B transcription audit

A document was created describing why Task 2B transcription was producing fragments or nearby words.

Main findings:

- short target words are difficult for ASR
- some recordings had too much silence or too little speech
- the AI service content index was not loaded
- fallback STT was configured as `mock` with an empty transcript
- Task 2B had naming drift between frontend upload metadata and stored task labels

Document:

- `docs/TASK2B_TRANSCRIPTION_AUDIT.md`

### Diagnostic audio timing documentation

A document was added explaining the shared recorder timing issue where audio could look long enough overall while still containing too little real speech.

Document:

- `docs/DIAGNOSTIC_AUDIO_TRANSCRIPTION_TIMING_BUG.md`

### CRLA summary

The CRLA summary page was redesigned toward the provided reference:

- more polished completion banner
- clearer CRLA total card
- Task 1 / Task 2A / Task 2B result cards
- Task 2B word result list
- responsive layout improvements
- duplicate "Continue to Passage Reading" button removed
- Miss Estelle image sizing adjusted after multiple passes so her body stays visible and the image is not overly cropped

Main file:

- `resources/js/Pages/Learner/CrlaSummary.vue`

### Reading intro

The reading intro page was redesigned based on the provided reference:

- Miss Vivian left-side panel
- large "Read aloud" prompt card
- guide message card
- bottom action area with "Start passage"

Main file:

- `resources/js/Pages/Learner/ReadingIntro.vue`

### Passage reading

The passage reading page was redesigned once and then reverted when requested.

Main file touched:

- `resources/js/Pages/Learner/PassageReading.vue`

### Comprehension questions

The comprehension page was redesigned based on the provided reference:

- larger question panel
- radio-style answer choices
- selected answer styling
- responsive layout
- font sizing reduced after feedback

Main file:

- `resources/js/Pages/Learner/ComprehensionQuestions.vue`

### Reading summary

The reading summary / completion page was redesigned from the provided reference:

- reading completion result cards
- incorrect words, accuracy, comprehension, final score, and reading level presentation
- guide character panel
- "See my path" / final action area
- font and layout adjustments after feedback

Main file:

- `resources/js/Pages/Learner/ReadingSummary.vue`

## Learner Dashboard Changes

The learner dashboard redesign attempt was reverted after feedback.

After the revert, focused dashboard changes were added:

- removed notification UI from the learner dashboard
- changed Help, Rewards, and Progress from inactive links into real pages
- footer "Need Help?" now routes to the Help page

Files changed:

- `resources/js/Pages/Learner/Dashboard.vue`
- `routes/web.php`
- `app/Http/Controllers/Learner/LearnerPageController.php`
- `resources/js/Components/Learner/LearnerSimplePageShell.vue`
- `resources/js/Pages/Learner/Progress.vue`
- `resources/js/Pages/Learner/Rewards.vue`
- `resources/js/Pages/Learner/Help.vue`

New routes:

- `GET /learner/progress`
- `GET /learner/rewards`
- `GET /learner/help`

The new pages show learner progress, reward milestones, and help guidance using the learner's current flow state and latest completed diagnostic attempt.

## Teacher and Admin Layout Changes

Notification bells, dropdowns, and placeholder notification data were removed from both teacher and admin layouts.

Files changed:

- `resources/js/Layouts/TeacherLayout.vue`
- `resources/js/Layouts/AdminLayout.vue`

The avatar/profile dropdowns remain in place.

## Shared Visual / Layout Changes

Several shared learner UI pieces were adjusted while matching the diagnostic reference designs:

- `resources/js/Components/Learner/AgentSpeakerPanel.vue`
- `resources/js/Components/SyncStatusBadge.vue`
- `resources/js/Layouts/LearnerLayout.vue`

These changes support the cleaner diagnostic screens and shared visual direction.

## Verification Done

Frontend build was run after major UI changes:

```bash
npm.cmd run build
```

The build passed.

Task 1 ASR normalization tests were run after the `Z` transcription fix:

```bash
php artisan test tests\Unit\AsrResponseNormalizerTest.php
```

The test passed.

The new learner routes were checked with:

```bash
php artisan route:list --path=learner
```

The Progress, Rewards, and Help routes were registered.

## Suggested Branch and Push Commands

Because the current local branch is `main`, create a feature branch before committing:

```bash
git switch -c feature/diagnostic-polish-and-dashboard-pages
```

Review changed files:

```bash
git status
```

Stage the work:

```bash
git add .
```

Commit:

```bash
git commit -m "Polish diagnostic flow and add learner support pages"
```

Push the new branch:

```bash
git push -u origin feature/diagnostic-polish-and-dashboard-pages
```

## Pull Request Description

### Title

Polish diagnostic flow and add learner support pages

### Summary

This PR updates the learner diagnostic experience, fixes a Task 1 letter transcription edge case, documents Task 2B transcription findings, removes unused notification dropdowns, and adds real learner pages for Progress, Rewards, and Help.

### Changes

- Added shared diagnostic step/progress behavior across diagnostic pages.
- Redesigned Task 1, Task 2B, CRLA summary, reading intro, comprehension, and reading summary screens to better match the provided ReaDirect references.
- Fixed Task 1 isolated-letter transcription so short letter recordings like `Z` can complete when ASR returns a usable transcript.
- Added a regression test for the Task 1 letter transcription completion behavior.
- Added documentation for Task 1 `Z`, Task 2B transcription findings, Task 2B resume/state behavior, and diagnostic audio timing.
- Removed notification bells/dropdowns from learner, teacher, and admin dashboard layouts.
- Added `/learner/progress`, `/learner/rewards`, and `/learner/help` pages.
- Added a learner page controller and shared simple learner page shell for the new pages.
- Removed duplicate CRLA summary action button and improved responsiveness.

### Verification

- Ran `npm.cmd run build`
- Ran `php artisan test tests\Unit\AsrResponseNormalizerTest.php`
- Checked learner routes with `php artisan route:list --path=learner`

### Notes for Reviewers

- The Task 1 transcription fix does not auto-mark `Z` as correct. It only allows a usable transcript to complete so existing scoring can decide correctness.
- Task 2B transcription still depends on ASR quality and content-index configuration. The audit document lists follow-up recommendations for stronger transcription reliability.
- The learner dashboard redesign was reverted; only focused navigation/page additions and notification removal remain.
