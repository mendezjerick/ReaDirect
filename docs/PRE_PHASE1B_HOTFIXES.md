# Pre-Phase 1B Hotfixes

## Why this hotfix was needed

After Phase 1 Dynamic Expected-Word Correction, short isolated words could look deceptively close to the expected answer even when Wav2Vec2 returned a clipped fragment. The same test pass also showed that the most confusable isolated letters should be removed from new letter-only selections, and that letter-only activities must treat wrong but usable recordings as completed attempts.

## Short-word truncation

Examples:

- Expected `fish`, raw ASR `fs`
- Expected `tree`, raw ASR `tr`

These fragments do not prove the learner only said `fs` or `tr`. The ASR may have missed weak vowels, ending sounds, or CTC-decoded only the consonant skeleton. The correction layer now marks these as `suspicious_fragment` when the raw transcript is blank, too short, vowel-less while the expected word has vowels, shorter than the configured length ratio, shaped like the expected consonant skeleton, or backed by weak phoneme coverage or bad audio.

Suspicious fragments are not accepted by spelling similarity alone. They can be accepted only when strong GOP or phoneme evidence supports the missing vowel or ending sounds. Bad audio, retry-required audio, mostly silent audio, or very short speech still requires retry through the normal ASR completion rules.

## Excluded isolated letters

New isolated-letter selections exclude:

- `B`
- `P`
- `D`
- `T`

Allowed isolated letters are centralized in `App\Support\IsolatedLetterSet`:

`A, C, E, F, G, H, I, J, K, L, M, N, O, Q, R, S, U, V, W, X, Y, Z`

This applies to new diagnostic Task 1 selections and new module letter-only practice/mastery selections. Existing locked attempts that already contain B/P/D/T still render and score safely.

The exclusion does not apply to words, rhymes, sentences, paragraphs, passages, datasets, reinforcement CSVs, phoneme rules, or correction logic. Words like `book`, `blue`, `dropped`, `time`, `tree`, `basket`, `table`, `puppy`, and `dog` remain valid content.

## Wrong-but-valid letter attempts

Letter-only activities separate correctness from completion:

- A valid wrong transcript is saved.
- The item is marked incorrect.
- `answered_at` is set after save/scoring succeeds.
- The learner can continue.

Examples:

- Expected `C`, raw `Z`: saved as incorrect and completed.
- Expected `C`, raw `banana`: saved as incorrect and completed.
- Expected `C`, empty transcript with `retry_required=true`: not completed; learner retries.

## Scoring impact

Letter scoring uses the selected item count. New selections contain 22 possible allowed letters, but individual assessments still score against the actual selected items, not a hardcoded 26-letter denominator.

## Future note

B/P/D/T may be restored later after stronger ASR, beam-candidate, or phoneme evidence improves isolated-letter reliability. Phase 1B passage-aware dynamic alignment repair was not implemented in this hotfix.
