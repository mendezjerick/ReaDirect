# Module Lesson Plan Reference

This document summarizes the current learner module lessons and the CSV fields that drive display, item selection, and scoring. Use this as the planning reference before changing lesson structure or CSV content.

## Source Files

The app-consumed seed files live in `database/seed-data/readirect`.

- `module1_letter_sound_activities_adaptive_v2.csv`
- `module2_word_reading_activities_adaptive_v2.csv`
- `module3_sentence_fluency_activities_adaptive_v2.csv`
- `module_activity_selection_rules.csv`

The module activity CSVs are item banks. They are not fixed screens. The lesson boxes and practice flow come from `module_activity_selection_rules.csv`, while the selected rows from the item banks are locked into `module_attempt_items` when a learner starts a module attempt.

## Current Lessons

Each module currently has four active learner-facing practice lessons. Each lesson selects 5 practice items. Mastery checks are separate and are not shown as overview lessons.

### Module 1: Letter and Sound Learning

| # | Lesson | Activity Key | Current Focus |
|---|---|---|---|
| 1 | Display Letter Pair | `letter_pair_identification` | Display `Aa`; learner says `A`. |
| 2 | Highlighted First Letter | `highlighted_first_letter` | Display a word with the first letter highlighted; learner says that letter. |
| 3 | First Letter | `first_letter_identification` | Display the word without a highlight; learner says the first letter. |
| 4 | Missing First Letter | `missing_first_letter` | Display `Cat - _at`; learner says the missing first letter. |

### Module 2: Word Reading

| # | Lesson | Activity Key | Current Focus |
|---|---|---|---|
| 1 | Display Word | `display_word_reading` | Display one word; learner reads that word. |
| 2 | Split Word | `split_word_reading` | Display word parts like `C + at`; learner reads the whole word. |
| 3 | Highlighted Rhyme Word | `highlighted_rhyme_word` | Display a rhyme group and highlight one word; learner reads the highlighted word only. |
| 4 | Highlighted Sentence Word | `highlighted_sentence_word` | Display a sentence and highlight the target word; learner reads the highlighted word only. |

### Module 3: Sentence Reading and Fluency

| # | Lesson | Activity Key | Current Focus |
|---|---|---|---|
| 1 | Simple Sentence | `simple_sentence_reading` | Read one full sentence accurately. |
| 2 | Comma Pause | `comma_pause_reading` | Read a sentence with a small pause at the comma. |
| 3 | Full-Stop Pause | `full_stop_pause_reading` | Read two short sentences with a stronger full-stop pause. |
| 4 | Mixed Punctuation Fluency | `mixed_punctuation_fluency` | Read mixed punctuation smoothly with accuracy and pacing. |

## Module Activity CSV Schema

The three module item-bank CSVs share the same wide schema:

```text
prompt_id
source_file
source_group
module_key
task_type
activity_type
prompt_text
expected_text
accepted_answers
difficulty
points
is_active
is_mastery_item
expected_phonemes
initial_phoneme
vowel_phonemes
final_phoneme
phoneme_pattern
skill_tag
error_focus
metadata
item_text_type
phoneme_count
syllable_estimate
has_cmudict_match
cmudict_missing_words
skill_group
target_position
target_phoneme
target_grapheme
word_family
rime_unit
onset_unit
enrichment_warnings
needs_manual_review
difficulty_score
difficulty_level
difficulty_factors
adaptive_bucket
recommended_for_error_type
remediation_priority
practice_role
mastery_candidate
review_candidate
min_required_attempts
cooldown_group
enrichment_status
word_count
sentence_length_bucket
fluency_metric_rule
target_read_time_seconds
min_fluent_time_seconds
max_fluent_time_seconds
target_wcpm
min_expected_wcpm
max_expected_wcpm
pace_feedback_rule
pace_mastery_required
```

## Display Fields

The learner prompt display is built from the locked item payload. Runtime display priority is:

```text
metadata.display_text
metadata.target_sentence
metadata.target_word
expected_answer / expected_text
prompt_text
```

The most important display-driving fields are:

```text
prompt_text
expected_text
metadata.display_text
metadata.display_format
metadata.target_letter
metadata.target_word
metadata.target_sentence
metadata.highlight_target
metadata.highlighted_letter
metadata.highlighted_word
metadata.split_word_display
metadata.rhyme_group
metadata.sentence_with_target
metadata.punctuation_focus
metadata.canonical_target
activity_type
module_key
```

For Module 1, the item bank carries the display format explicitly:

```text
letter_pair_identification -> Aa
highlighted_first_letter -> Cat, highlight C
first_letter_identification -> Cat
missing_first_letter -> Cat - _at
```

For Module 2, simple lowercase word display values are capitalized for the learner view, and highlight metadata controls visual highlighting:

```text
cat -> Cat
C + at -> read cat
cat   bat   mat -> highlight target word
I can read cat. -> highlight cat
```

## Scoring Basis

### Module 1 and Module 2

Correctness is based mainly on `accepted_answers`.

`accepted_answers` is pipe-delimited:

```text
cat
A|a
```

The learner transcript and accepted answers are normalized before comparison:

- lowercase
- remove punctuation
- collapse repeated whitespace
- exact compare against each accepted answer

Important scoring fields for Modules 1 and 2:

```text
expected_text
accepted_answers
points
activity_type
module_key
is_active
is_mastery_item
```

### Module 3

Sentence correctness is based on the expected sentence:

```text
expected_text
metadata.expected_answer
metadata.target_sentence
```

The sentence scorer checks:

- word accuracy
- skipped words
- substitutions
- insertions
- pacing, when required

Module 3 uses timing and pacing fields when present:

```text
target_read_time_seconds
min_fluent_time_seconds
max_fluent_time_seconds
target_wcpm
min_expected_wcpm
max_expected_wcpm
pace_feedback_rule
pace_mastery_required
```

If `pace_mastery_required` is true, the learner must read the sentence accurately and land in the fluent pace band to receive full correctness.

## Selection Rule CSV

`module_activity_selection_rules.csv` controls which lesson boxes appear and how many items each lesson locks.

Top-level columns:

```text
prompt_id
source_file
source_group
module_key
task_type
activity_type
prompt_text
expected_text
accepted_answers
difficulty
points
is_active
is_mastery_item
expected_phonemes
initial_phoneme
vowel_phonemes
final_phoneme
phoneme_pattern
skill_tag
error_focus
metadata
item_text_type
phoneme_count
syllable_estimate
has_cmudict_match
cmudict_missing_words
skill_group
target_position
target_phoneme
target_grapheme
word_family
rime_unit
onset_unit
enrichment_warnings
needs_manual_review
difficulty_score
difficulty_level
difficulty_factors
adaptive_bucket
recommended_for_error_type
remediation_priority
practice_role
mastery_candidate
review_candidate
min_required_attempts
cooldown_group
enrichment_status
word_count
sentence_length_bucket
```

Important metadata keys:

```json
{
  "id": "M1-R001",
  "module_key": "module_1",
  "activity_type": "letter_pair_identification",
  "is_active": 1,
  "practice_item_count": 5,
  "mastery_item_count": 0
}
```

Current rule:

- each module has 4 active practice lesson types
- each practice lesson locks 5 items
- mastery check is separate from overview lessons

## Revamp Notes

- Change lesson boxes by editing `module_activity_selection_rules.csv` and the overview labels in `ModuleExperienceService`.
- Change item content by editing the module item-bank CSVs.
- Change scoring acceptance by editing `accepted_answers` for Modules 1 and 2.
- Change Module 3 sentence/pacing behavior through `expected_text`, sentence text metadata, and pacing fields.
- Keep `activity_type` stable unless you also update selectors, labels, filters, tests, and any feedback mappings that reference that key.
