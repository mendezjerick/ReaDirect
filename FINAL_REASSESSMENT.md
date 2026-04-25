# Final Reassessment

Phase 10 adds a final reassessment flow for learners who have completed module practice and are ready to compare progress against their first diagnostic assessment.

## Purpose

The final reassessment repeats the same assessment structure as the diagnostic flow:

- Task 1 letter pronunciation
- Task 2A rhyming words when required by Task 1 routing
- Task 2B word-in-sentence reading
- 50-word passage reading
- 5 comprehension questions

Final reassessment uses the same rule-based scoring services as diagnostic assessment. It does not change historical diagnostic records.

## Data Model

Final reassessments are stored in `assessment_attempts` with:

```text
attempt_type = final_reassessment
baseline_assessment_attempt_id = initial diagnostic attempt id
comparison_summary = JSON improvement summary
```

Item responses continue to use `assessment_task_responses`. Audio continues to use private `audio_files` storage and Phase 9 transcript fields.

## Item Selection

When an initial diagnostic attempt exists, the final reassessment clones the locked diagnostic prompt snapshots for comparable tasks. If a task was not present in the baseline, ReaDirect falls back to the active item bank.

This preserves auditability: final responses keep their own timestamps, audio, transcripts, scores, and rule identifiers.

## Scoring

Final reassessment uses the existing pipeline:

```text
audio -> STT/manual transcript -> TranscriptSanitizer -> AnswerMatchingService -> scoring services
```

Official scoring remains rule-based:

- `CrlaScoringService`
- `ReadingComprehensionScoringService`
- `AnswerMatchingService`

STT supplies transcript text only.

## Improvement Calculation

`app/Services/Assessment/FinalAssessmentComparisonService.php` compares the baseline diagnostic attempt with the final reassessment.

Metrics include:

- Task 1 score
- Task 2A score
- Task 2B score
- CRLA total score
- Reading accuracy
- Comprehension percentage
- Final reading score

The comparison stores:

- `initial_scores`
- `final_scores`
- `deltas`
- `percent_change`
- `summary`

## Learner Summary

The learner final summary page shows:

- final CRLA score
- final reading score
- CRLA growth
- reading score growth
- task and accuracy deltas

Learners see friendly progress language, not internal scoring formulas.

## Teacher View

Teacher pages now include final reassessment information:

- dashboard count for completed final reassessments
- learner detail final reassessment card
- final comparison CSV export
- existing assessment review can open final attempts because final attempts use the same assessment review schema

## Manual Verification

Learner:

1. Open `/final-assessment/start`.
2. Complete Task 1.
3. Continue through routed tasks.
4. Complete passage and comprehension.
5. Confirm the final summary shows initial vs final growth.

Teacher:

1. Open teacher dashboard.
2. Confirm final reassessment completed count updates.
3. Open learner detail.
4. Review final reassessment.
5. Download Final Comparison CSV.

Error paths:

1. Leave transcript blank without audio; progression should be blocked.
2. Record audio with STT disabled; manual transcript fallback should still work.
3. Confirm initial diagnostic records are unchanged.
