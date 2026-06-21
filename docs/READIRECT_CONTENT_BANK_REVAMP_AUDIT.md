# ReaDirect Content Bank Revamp Audit

## Current Implementation Before Changes

- Laravel is the official scoring and routing layer. Vue/Inertia renders learner/admin pages. FastAPI/ASR remains speech processing only.
- Official assessment content is imported from `database/seed-data/readirect/*.csv` by `DiagnosticContentSeeder` and `ModuleContentSeeder`.
- Assessment selection is handled by `AssessmentItemSelectionService`.
- Diagnostic and final flows already used CRLA Task 1A, Task 2A yes/no rhyme decisions, Task 2B, passage reading, and comprehension, but passage selection was auto-locked before this update.
- Module practice/mastery content is selected from active `module_activity` LearningContent rows through `ModuleActivitySelectionService`.
- Reinforcement CSVs live in `ReaDirect-AI-ASR/reinforcement-learning` and were not replaced.

## Files Changed

- `database/seed-data/readirect/task2a_rhyming_words.csv`
- `database/seed-data/readirect/task2b_word_in_sentence.csv`
- `database/seed-data/readirect/reading_passages.csv`
- `database/seed-data/readirect/comprehension_questions.csv`
- `database/seed-data/readirect/module1_letter_sound_activities.csv`
- `database/seed-data/readirect/module2_word_reading_activities.csv`
- `database/seed-data/readirect/module3_sentence_fluency_activities.csv`
- Mirrored CSV copies under `content-bank/export/...` and `ReaDirect-AI-ASR/ReaDirect-Dataset/...`
- Laravel seeders, assessment routing, story selection pages, true sandbox wiring, simulator scoring, and tests.

## CSV Updates

- Task 1A was retained as the existing A-Z bank.
- Task 2A now has exactly 10 active yes/no rhyme decision rows.
- Task 2B now has exactly 10 active easy word-in-sentence rows; older difficult rows remain inactive.
- Reading passages now have only two active assessment stories: `PASS-001` and `PASS-002`.
- Comprehension now has exactly four active multiple-choice questions per active story.
- Module CSVs retain existing row IDs, but active rows were limited to easier content. Old/harder rows remain present and inactive.

## Words Removed Or Marked Inactive

- Task 2B inactive examples include `box`, `fish`, `tree`, `book`, `car`, `hand`, `rock`, `duck`, and `moon`.
- Old assessment passages using Arthur, Merlin, Robin Hood, Odysseus, carabao, and other harder story content are inactive for assessment selection.
- Module 2 active content no longer uses the hard-word list above.

## Words Retained

- Task 1A letters remain available in the bank.
- Reinforcement CSV words were retained.
- Easy module and assessment words retained include `cat`, `hat`, `dog`, `sun`, `pen`, `cup`, `bed`, `hop`, `tap`, and related CVC words.

## Easy Word Inventory

`cat, hat, bat, mat, sat, dog, log, sun, run, pen, hen, cup, pup, bug, bed, red, hop, top, tap, map, pin, pan, sit, lip`

## New Task 2A Rhyme Pairs

- Rhyming: `cat/hat`, `sun/run`, `dog/log`, `cup/pup`, `bed/red`, `hop/top`
- Non-rhyming: `map/sit`, `pen/bug`, `bat/lip`, `hen/tap`
- Confirmation: the 10 active rows contain 20 unique words; no word is repeated.
- Task 2A remains no-ASR and click-scored only.

## New Task 2B Items

1. `I see a cat.` Target: `cat`
2. `The dog can run.` Target: `dog`
3. `We sit in the sun.` Target: `sun`
4. `My hat is red.` Target: `hat`
5. `I have a pen.` Target: `pen`
6. `The cup is big.` Target: `cup`
7. `A bug can run.` Target: `bug`
8. `The bed is soft.` Target: `bed`
9. `Ben can hop.` Target: `hop`
10. `Sam can tap.` Target: `tap`

## New Passage Records

- `PASS-001`, Story 1: `Rosa and the Kite`, 50 words.
- `PASS-002`, Story 2: `Lena and the Seed`, 50 words.
- `PASS-003` through `PASS-010` remain in the CSV but are inactive for assessment selection.

## Comprehension Records

- `CQ-001` through `CQ-004` link to `PASS-001`.
- `CQ-005` through `CQ-008` link to `PASS-002`.
- Each active question has four choices, one `correct_choice`, and accepted answers matching the correct displayed choice text.
- Legacy questions remain in the CSV but are inactive.

## Reinforcement Status

- `ReaDirect-AI-ASR/reinforcement-learning/word-reinforcement.csv` retained.
- `ReaDirect-AI-ASR/reinforcement-learning/letter-reinforcement.csv` retained.
- No reinforcement CSV was replaced or deleted.

## True Sandbox Updates

- Task 2A sections are labeled as rhyme decisions and marked no-ASR.
- Active content filtering prevents inactive old passages/module rows from appearing in sandbox item lists.
- Task 2A decision rows show expected Yes/No behavior without a recorder.

## Module Mastery Simulator Updates

- Comprehension total is now 4.
- Low Task 1A branch now ends after Task 2A with Task 2B and passage fields zeroed.
- Passage scoring is applied only when CRLA passage eligibility is true.
- Simulator labels now distinguish Task 2A and Task 2B.

## Assumptions

- The enriched CSV index is a legacy/generated support source. Seeder row enrichment now overrides stale index hints for revised rows.
- Existing row IDs were preserved where possible. Old rows were marked inactive rather than deleted to avoid breaking historical references.
- Diagnostic and final assessment share the same active assessment content bank unless future separate cycle content is added.
