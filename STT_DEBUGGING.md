# STT Debugging

STT debug screens are available to system admins in Testing / QA Mode.

## Accuracy Workflow

1. Open `/admin/testing`.
2. Select a learner and create a sandbox attempt.
3. Complete or upload an audio response.
4. Open the related STT debug page.
5. Compare raw transcript, normalized transcript, expected answer, accepted answers, confidence, and score.

## Transcript Corrections

Transcript correction workflows should preserve source labels such as `manual`, `stt_auto`, `teacher_review`, and `admin_review`.

Corrections to real scored attempts should require explicit confirmation. Sandbox attempts are safe for testing correction effects.

## Privacy

Learner voice is private data. Do not expose public audio URLs, do not log raw audio, and do not include private learner data in shared debugging exports.
