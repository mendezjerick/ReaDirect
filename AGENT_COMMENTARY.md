# ReaDirect Agent Commentary

Phase 7.5 makes the three fixed ReaDirect agents react after learner answers.

This is not a help chat, not character selection, and not a new agent.

## Agents

- Assessment Agent: diagnostic and reassessment pages
- Coach + Feedback Agent: learning module practice and mastery checks
- Evaluator / Recommendation Agent: summaries, placement, and next-step results

## Modes

### Assessment Neutral

Used during strict diagnostic assessment.

The Assessment Agent may acknowledge the learner and move forward:

- "Thank you. Let us continue."
- "Good effort. Let us go to the next one."
- "I heard your answer. Let us keep going."

It must not:

- reveal the correct answer
- say whether the learner was correct
- say the answer was close
- give hints
- coach pronunciation

Diagnostic assessment must stay standardized and fair.

### Module Coaching

Used during learning module practice and mastery checks.

The Coach + Feedback Agent can:

- praise correct answers
- explain closeness
- encourage retry
- point to a first sound, ending sound, middle sound, or skipped word

Official correctness and score still come from rule-based services before any commentary is generated.

### Evaluator Summary

Used on results and next-step pages.

The Evaluator / Recommendation Agent can explain the already-computed decision:

- moving to another module
- repeating a module
- extra drills
- reassessment placeholder
- no module needed

It cannot change the decision.

## Closeness Computation

`AnswerSimilarityService` normalizes answers and uses Levenshtein distance to label similarity:

- `exact`
- `very_close`
- `close`
- `somewhat_close`
- `far`
- `blank`

Similarity is only for feedback wording. It never overrides official correctness.

## LLM Use

The LLM may be used for commentary wording in `module_coaching` and `evaluator_summary` modes.

The LLM is not used in `assessment_neutral` mode.

The LLM receives sanitized result context only. It does not receive API keys, private notes, raw audio, or unnecessary learner identifiers.

## Fallback Templates

Fallback templates live in:

```text
database/seed-data/readirect/agent_commentary_templates.csv
```

The application also has built-in fallback messages so it remains stable if CSV importers are not present.

## Safety Rules

Commentary is limited to short, child-friendly messages.

Blocked language includes:

- wrong
- failed
- bad
- poor
- stupid
- dumb
- cannot read
- disorder
- disability
- diagnosis

Assessment neutral output also blocks hints, closeness language, correctness language, and expected-answer reveals.

If generated output is unsafe, empty, or too long, ReaDirect falls back to a safe template.

## Examples

Allowed assessment comment:

```text
Thank you. Let us continue.
```

Disallowed assessment comment:

```text
That was close. Try the /t/ sound.
```

Allowed module comment:

```text
Good try! That was very close. Let us fix one small sound.
```

Allowed evaluator comment:

```text
You are moving to Module 2. Now we will practice reading words.
```
