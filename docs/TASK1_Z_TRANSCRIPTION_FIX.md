# Task 1 Z Transcription Fix

## What Happened

Task 1 asks the learner to say one isolated letter. For the letter `Z`, the recording can be very short, especially when the learner says the /z/ sound or a quick "zee".

The AI service was still returning a recognized transcript, but it also marked the recording as unreliable. Recent local examples included:

- `they`
- `v`
- `w`
- `b`
- `a b c d f`

Because Laravel trusted the retry/quality flag first, the upload was blocked with:

`Please record again. The audio was not reliable enough to score.`

That made the learner repeat the same item even though there was enough transcript text to score the attempt as correct or incorrect.

## Fix

Task 1 letter prompts now use this rule:

- If the letter recording has a usable transcript, allow the item to complete.
- If the transcript is blank, silence, noise, or inaudible, still require a retry.
- Do not force the answer to the expected letter. The transcript is saved as recognized, so scoring can still mark it wrong when it does not match.

This is handled in:

`app/Services/ASR/AsrResponseNormalizer.php`

The regression test is in:

`tests/Unit/AsrResponseNormalizerTest.php`

## Why This Is Safe

The fix does not auto-pass `Z`. It only prevents Task 1 from getting stuck when ASR has text but labels the short letter audio as uncertain.

For example:

- Prompt: `Z`
- Transcript: `Z` or `zee` - can score correct.
- Transcript: `they`, `v`, or `b` - can submit, but should score incorrect.
- Transcript: empty or silence - still blocked and asks the learner to record again.
