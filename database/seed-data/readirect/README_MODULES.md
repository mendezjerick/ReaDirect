# ReaDirect Phase 3 Module Seed Data

These CSV files are development item banks for the Phase 3 learning module flow. They are not fixed learner screens. A module attempt should select active items from these banks, save the selected rows into `module_attempt_items`, and reuse that locked sequence for the same attempt.

## Files

- `module1_letter_sound_activities_adaptive_v2.csv` contains the active Module 1 letter and sound v2 bank. Isolated `P`, `D`, `B`, and `Z` rows remain inactive.
- `module2_word_reading_activities_adaptive_v2.csv` contains the active Module 2 word reading v2 bank. Active target words are three-letter CVC words only.
- `module3_sentence_fluency_activities_adaptive_v2.csv` contains the active Module 3 beginner sentence bank and the optional Advanced Module sentence fluency bank, including WCPM timing targets. Required Module 3 sentences use the Module 2 CVC vocabulary, with only `The`, `is`, and `and` as helper words.
- `module_feedback_templates.csv` contains reusable, child-friendly Miss Ciel feedback templates.
- `module_activity_selection_rules.csv` defines how many active items to lock per activity type and how many mastery-check items to lock.

## Selection Rules

Module CSVs are item banks. When a learner starts or resumes a module, the system creates or reuses a `module_attempt`. Practice activities and mastery checks should lock selected rows into `module_attempt_items` with a `prompt_snapshot`. This protects old attempts if seed content changes later.

Practice activities may select a small set of active items for the requested activity type. Mini mastery checks select 10 active mastery items for the module attempt. The UI should read from locked `module_attempt_items`, not directly from random item-bank queries.

## Feedback and Decisions

Miss Ciel feedback is coaching text only. Official mastery decisions must use `ModuleMasteryService` only:

- Module 1: `>= 90` move to Module 2, `0-89` repeat Module 1.
- Module 2: `>= 90` move to Module 3, `60-89` repeat Module 2, `< 60` return to Module 1.
- Module 3: `>= 90` proceed to final reassessment placeholder, `70-89` repeat Module 3, `< 70` return to Module 2.
- Advanced Module: optional only after a perfect Final Assessment; `>= 90` completes the optional module and grants the separate advanced star, `< 90` repeats the Advanced Module.

LLM feedback can be added later through a separate service boundary. These seed files are original sample content and can later be replaced with official ARAL-aligned content.
