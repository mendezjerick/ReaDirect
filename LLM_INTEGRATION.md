# ReaDirect LLM Integration

Phase 7 enables real OpenAI-backed wording for the Coach + Feedback Agent only.

## What The LLM Does

- Generates short, child-friendly feedback wording for learning module practice and mastery items.
- Uses sanitized context only.
- Falls back to template feedback when disabled, missing a key, rejected by safety checks, or unavailable.

## What The LLM Does Not Do

- It does not score answers.
- It does not classify CRLA or reading level.
- It does not decide module placement.
- It does not decide module mastery.
- It does not override recommendations.
- It does not process audio or perform speech-to-text.
- It does not access the database directly.

Official scoring and decisions stay in deterministic services such as `CrlaScoringService`, `ReadingComprehensionScoringService`, `ModulePlacementService`, `ModuleMasteryService`, and `RecommendationService`.

## Enabled Agent

- Coach + Feedback Agent: LLM-enabled for feedback wording.
- Assessment Agent: fixed scripts and rule-based behavior.
- Evaluator / Recommendation Agent: rule-based decisions only.

## Environment Variables

Place these in the server `.env` file:

```env
OPENAI_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT_SECONDS=30
```

For local development, set:

```env
OPENAI_ENABLED=true
OPENAI_API_KEY=sk-your-local-development-key
OPENAI_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT_SECONDS=30
```

Then run:

```powershell
php artisan config:clear
```

Never commit `.env`.

## API Key Ownership

Development keys may belong to the developer locally. Production keys must belong to the client or government organization.

The government/client should:

1. Create or use its own OpenAI organization/project.
2. Generate its own API key in the OpenAI Platform dashboard.
3. Store the key only in the production server `.env`.
4. Rotate or revoke the key according to its own security policy.

The developer should not provide a personal API key for production.

## Fallback Behavior

The app uses template feedback if:

- `OPENAI_ENABLED=false`
- `OPENAI_API_KEY` is empty
- OpenAI returns an error
- The request times out
- The model is unavailable
- The generated output is empty, too long, or unsafe

Learners should not see technical errors.

## Safety Rules

Generated output is trimmed, markdown is removed, and output is limited. The app rejects harsh or inappropriate wording such as:

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

Rejected output falls back to the existing child-friendly template.

## Privacy Notes

- API keys stay server-side.
- API keys are never sent to Vue, Inertia props, browser JavaScript, or database records.
- Prompt context avoids learner names, emails, school identifiers, private notes, and raw audio.
- LLM interaction logs store sanitized summaries only.

## Troubleshooting

Missing API key:

```powershell
php artisan config:clear
```

Confirm `.env` has `OPENAI_ENABLED=true` and `OPENAI_API_KEY=...`.

Invalid API key:

- The app falls back to template feedback.
- Check Laravel logs for a sanitized OpenAI error.
- Replace the key in `.env`.

Timeout:

- The app falls back to template feedback.
- Increase `OPENAI_TIMEOUT_SECONDS` only if needed.

Model not available:

- Confirm `OPENAI_MODEL` is available to the configured OpenAI project.
- Use another model value in `.env`.

Fallback being used:

- Check `llm_interactions.fallback_used`.
- Check `llm_interactions.safety_status`.
- Confirm config cache was cleared after `.env` changes.
