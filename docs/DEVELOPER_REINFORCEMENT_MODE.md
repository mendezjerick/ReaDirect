# Developer Reinforcement Mode

Developer reinforcement mode is an admin-only correction-memory workflow. It is not true machine-learning reinforcement training and does not update the Wav2Vec2 model. When enabled, Laravel marks authenticated admin/developer ASR requests so the AI service can write qualifying incorrect transcripts into CSV correction memory.

Warning shown in the admin UI:

> Developer reinforcement mode writes incorrect ASR outputs into correction memory. Turn this off before normal learner testing.

## How To Use

1. Open the admin dashboard.
2. Turn Developer Reinforcement Mode ON.
3. Use admin testing/sandbox flow to open a letter, word, rhyme, sentence, paragraph, or passage activity.
4. Record the expected answer.
5. If the AI service saves a correction, admin debug views show the mode, saved status, target file, and reason.
6. Repeat the same item and confirm `corrected_transcript` and `displayed_transcript` become the expected answer.
7. Turn Developer Reinforcement Mode OFF before learner testing.

Learners cannot see the toggle and Laravel never sends `developer_reinforcement_enabled: true` unless the current authenticated user is an admin/developer.

## CSV Files

The AI repo stores correction memory in:

- `ReaDirect-AI-ASR/reinforcement-learning/letter-reinforcement.csv`
- `ReaDirect-AI-ASR/reinforcement-learning/word-reinforcement.csv`

Only `letter` prompts use `letter-reinforcement.csv`.

These prompt types use `word-reinforcement.csv`:

- `word`
- `rhyme`
- `rhyming_word`
- `sentence`
- `paragraph`
- `passage`
- `final_sentence`
- `reading_passage`

## Examples

For expected `C` and raw ASR `See`, a letter correction goes to `letter-reinforcement.csv`.

For expected `Leo` and raw ASR `Layo`, a word correction goes to `word-reinforcement.csv`.

## Safety

The AI service skips writes for empty expected text, empty raw transcript, already-equal normalized text, retry-required/bad audio, uncertain audio, unsupported prompt types, non-admin callers, disabled mode, accepted transcripts that already had a correction strategy, and duplicate correction pairs.

The AI service writes an audit row to `reinforcement-learning/reinforcement-audit.log` for saved, duplicate, and skipped attempts.
