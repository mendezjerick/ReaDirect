# Task 2B State / ID / Resume Bug

This note explains the old Task 2B bug where:

- clicking `Check sentence` did not move to the correct next step
- the page sometimes showed a saved/submitted-looking state even though the learner could not continue
- after reload, the learner was taken back to the step the system considered unfinished

## Short version

The behavior came from **two different state systems** disagreeing:

1. the **frontend page state** in Vue
2. the **saved backend progress state** in Laravel/PostgreSQL

When those two got out of sync, the UI could look “done enough”, but the backend still considered the task incomplete. On reload, the backend won, and the learner was redirected to the step it believed was still unfinished.

## Root cause 1: submitted item IDs were compared too strictly

Before the fix, the final Task 2B submit check compared:

- expected `assessment_attempt_item_id` values from the database
- submitted `assessment_attempt_item_id` values from the form

The request was posted with `FormData`, so IDs could arrive as strings like `"241"` instead of integers like `241`.

That mattered because the controller used an exact array comparison when checking whether the full item set had been submitted.

Relevant backend methods:

- [DiagnosticAssessmentController.php](../app/Http/Controllers/Learner/DiagnosticAssessmentController.php)
- [FinalAssessmentController.php](../app/Http/Controllers/FinalAssessmentController.php)

Relevant method name:

- `validateSubmittedAssessmentItemSet()`

### Why that broke the flow

If the expected IDs were:

```text
[241, 242, 243, ...]
```

and the submitted IDs were effectively:

```text
["241", "242", "243", ...]
```

the controller could reject the submission even though the learner had answered the correct items.

That produced the message:

```text
Almost there! Continue from the current step.
```

### Effect on redirect/reload

Because that validation failed:

- Task 2B was **not scored**
- `task_2b_score` stayed `null`
- `crla_total_score` stayed `null`
- the attempt was still considered in-progress

So when the learner reloaded, the flow service correctly sent them back to Task 2B, because from the database point of view Task 2B had never actually completed.

## Root cause 2: the UI treated uploaded audio as “answered” too early

There was also a separate frontend state bug in Task 2B.

Relevant page files:

- [Task2BWordInSentence.vue](../resources/js/Pages/Learner/Task2BWordInSentence.vue)
- [FinalAssessment/Task2BWordInSentence.vue](../resources/js/Pages/Learner/FinalAssessment/Task2BWordInSentence.vue)

Task 2B is not really done just because an audio file exists. It should only count as done when there is a **usable transcript** for the highlighted target word.

The important frontend rules now are:

- `hasUsableTranscript(...)`
- `hasAcceptedTranscript(...)`
- `hasAnswerOrAudio(...)`

### What went wrong before

If the learner recorded audio and upload succeeded, the UI could look like:

- audio saved
- submitted
- green confirmation

but the transcript might still be:

- empty
- only one letter
- only a partial sound

That meant the screen could visually suggest “done”, while the backend still had no valid completed answer for scoring.

### Why reload looked strange

When the page reloads, it does **not** trust the old local Vue state. It rebuilds the page from the database:

- `saved_response`
- `answered_at`
- attempt-level scores/status

So if the UI had looked complete locally, but the backend never accepted that item as truly answered, the page reopened at the first unfinished item.

That made it feel like the app “jumped backward”, but it was really restoring the backend truth.

## Root cause 3: resume routing is based on backend progress, not current screen position

The learner flow is restored by:

- [LearnerFlowService.php](../app/Services/LearnerFlowService.php)

Important methods:

- `diagnosticResumeRouteName()`
- `finalResumeTaskKey()`
- `diagnosticStepAllowed()`
- `finalTaskAllowed()`

### Diagnostic resume logic

For diagnostic flow, the next allowed page is derived from attempt fields such as:

- `task_1_score`
- `task_2a_score`
- `task_2b_score`
- `crla_total_score`
- `reading_accuracy`
- `final_reading_score`

If `task_2b_score` is still `null`, the flow service still considers Task 2B unfinished.

### Per-item resume logic inside the page

Inside the controller, the current card index is restored with:

- `initialIndexForItems()`

That method finds the **first item whose `answered_at` is still null**.

Relevant controller methods:

- [DiagnosticAssessmentController.php](../app/Http/Controllers/Learner/DiagnosticAssessmentController.php)
- [FinalAssessmentController.php](../app/Http/Controllers/FinalAssessmentController.php)

### Why this matters

After reload:

1. Laravel decides which task page is still allowed
2. the task page decides which item index is still unanswered

So the learner is returned to:

- the unfinished task, and then
- the first unfinished item inside that task

This is why reload sometimes sent the learner to the “supposed” correct step even when the previous screen felt further ahead.

## The exact sequence of the old bug

Typical failure sequence:

1. The learner records audio on Task 2B.
2. The frontend marks the item as looking submitted/saved too early, or the final submit payload sends item IDs in a shape the backend rejects.
3. The learner clicks `Check sentence`.
4. The backend validation rejects the submit or the item is still not considered fully answerable.
5. Task 2B score is not written.
6. The attempt remains incomplete.
7. On reload, the flow service sends the learner back to Task 2B.
8. Inside Task 2B, `initialIndexForItems()` returns the first item whose `answered_at` is still null.

That is the specific reason the redirect order felt wrong.

## Fixes that were applied

### 1. ID normalization in submit validation

The controllers now normalize expected and submitted item IDs to integers before comparing them.

That removed the false mismatch between:

- database integers
- `FormData` string values

### 2. Task 2B now requires a usable transcript before counting the item as answered

The page now treats an item as accepted only when:

- an uploaded audio file exists, and
- the transcript is usable for the highlighted word

This removed the fake “submitted” state for partial letter-only transcripts.

### 3. Resume behavior now makes more sense because frontend and backend state match

Once the item is only marked answered after a usable transcript exists, the visual state and saved state line up much better.

## How to debug this class of bug again

If this happens again, check these in order:

1. `responses[*].assessment_attempt_item_id`
   - confirm the submitted item set exactly matches the locked attempt items
2. `assessment_attempt_items.answered_at`
   - confirm the expected item was actually marked answered
3. `assessment_attempts.task_2b_score`
   - confirm the task itself was actually scored
4. `LearnerFlowService`
   - confirm the resume route still points to the step you expect
5. frontend Task 2B state
   - confirm the item is only shown as accepted when transcript quality is actually usable

## Main takeaway

The redirect was not random.

It happened because the backend uses persisted progress as the source of truth:

- if the submitted item IDs do not validate, or
- if the transcript is not good enough to count as answered,

then Task 2B is still incomplete in storage, and reload will return the learner to that unfinished spot.
