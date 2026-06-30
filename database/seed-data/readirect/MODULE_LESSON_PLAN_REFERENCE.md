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
| 1 | Letter Sound Warm-Up | `hear_and_repeat` | Start with clear letter-sound practice. |
| 2 | Look and Say | `see_letter_say_sound` | Look at the letter, then say its sound. |
| 3 | Letter Sound Check | `match_sound_to_letter` | Check the sound that belongs with the letter. |
| 4 | Quick Sound Practice | `sound_drill` | Repeat letter sounds for faster recall. |

### Module 2: Word Reading

| # | Lesson | Activity Key | Current Focus |
|---|---|---|---|
| 1 | Read One Word | `read_word` | Read short words clearly, one at a time. |
| 2 | Word Family Practice | `word_family_drill` | Read words that share the same ending pattern. |
| 3 | Similar Word Practice | `minimal_pair` | Read similar-looking words with careful sounds. |
| 4 | Word Accuracy Check | `word_accuracy_challenge` | Read each word clearly and check every sound. |

### Module 3: Sentence Reading and Fluency

| # | Lesson | Activity Key | Current Focus |
|---|---|---|---|
| 1 | Read One Sentence | `read_sentence` | Read the whole sentence clearly from start to finish. |
| 2 | Guided Sentence Practice | `read_with_coach` | Read a sentence, then Miss Ciel can guide your next try. |
| 3 | Steady Sentence Reading | `timed_sentence_reading` | Read at a steady pace without rushing. |
| 4 | Pause and Pace Practice | `pause_practice` | Read with small pauses so the sentence makes sense. |

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
metadata.target_word
metadata.target_sentence
activity_type
module_key
```

For Module 1, the frontend formats isolated letter prompts as upper/lower pairs when possible:

```text
A -> Aa
B -> Bb
```

For Module 2, simple lowercase word display values are capitalized for the learner view:

```text
cat -> Cat
```

## Scoring Basis

### Module 1 and Module 2

Correctness is based mainly on `accepted_answers`.

`accepted_answers` is pipe-delimited:

```text
Cat|cat
A|a|Aa|short a|shorta
```

The learner transcript and accepted answers are normalized before comparison:

- lowercase
- remove punctuation
- collapse repeated whitespace
- exact compare against each accepted answer

Some spoken letter aliases are also accepted for isolated letters. Examples:

```text
c -> see / sea
o -> oh
u -> you / yew
x -> ex
```

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
  "activity_type": "hear_and_repeat",
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
