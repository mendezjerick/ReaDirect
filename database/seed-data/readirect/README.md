# ReaDirect Phase 2 Diagnostic Seed Data

These files are development seed-content banks for the ReaDirect Phase 2 diagnostic assessment. The CSV files are item banks, not fixed assessments. Future Laravel seeders/importers can load these rows into database tables, and official ARAL-aligned content can replace them later while preserving the headers.

These files are not importers by themselves unless importer logic is added separately.

## Item Bank Behavior

When an assessment attempt starts, ReaDirect should randomly select the required number of active items from the relevant item bank, save those selected items to `assessment_attempt_items`, and reuse that locked selection for the same attempt.

Do not reshuffle items during the same assessment attempt. The UI should read from the locked items, not directly from a random item-bank query on each page load.

## Files

- `task1_letter_pronunciation.csv`: Letter pronunciation item bank with A-Z. Task 1 selects exactly 10 active letters from 26.
- `task2a_rhyming_words.csv`: Yes/no rhyme-decision bank. Task 2A selects exactly 10 active prompts when Task 1 score is 0-6.
- `task2b_word_in_sentence.csv`: Word-in-sentence item bank. Task 2B selects exactly 10 active sentence items when Task 1 score is 7-10.
- `reading_passages.csv`: Reading passage bank. Eligible learners choose exactly 1 active 50-word story.
- `comprehension_questions.csv`: Four multiple-choice questions linked to each active passage. The selected passage determines which four questions are used.
- `agent_scripts.csv`: Fixed scripts for Miss Vivian and Miss Estelle, plus reusable Miss Ciel coaching scripts.
- `feedback_templates.csv`: Reusable feedback template bank selected by module, error type, and severity.
- `reading_classification_rules.csv`: Deterministic final reading score bands. Reading classification must use `final_reading_score` only.
- `module_placement_rules.csv`: Deterministic module placement rules using CRLA level plus reading classification.

## Assessment Mapping

CRLA Task 1 uses 10 locked letter items. If the Task 1 score is 0-6, the learner proceeds only to Task 2A; Task 2B and passage reading are recorded as 0. If the Task 1 score is 7-10, Task 2A receives an automatic score of 10 and the learner proceeds directly to Task 2B.

Reading comprehension uses one selected 50-word passage and its four linked multiple-choice questions. The score inputs are:

- `accuracy_percentage = 100 - (incorrect_words * 2)`
- `comprehension_percentage = (correct_answers / 4) * 100`
- `final_reading_score = (comprehension_percentage * 0.60) + (accuracy_percentage * 0.40)`

Reading classification follows only `final_reading_score`, not raw accuracy ranges or raw comprehension answer counts.

## Locked Selection Table

Selected items should be stored in `assessment_attempt_items` with a prompt snapshot. This protects old assessment records if item-bank content is edited later.
