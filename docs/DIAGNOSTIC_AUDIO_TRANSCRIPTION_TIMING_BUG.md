# Diagnostic Audio Transcription Timing Bug

This note explains the older bug where it looked like:

- only the first second of the learner's speech was being transcribed
- the transcript came back as only one letter, one sound, or a very short fragment
- the issue seemed to affect more than one diagnostic step

## Short version

The main problem was **not** that the audio file was always physically cut to exactly one second.

The real issue was that the shared recorder logic could accept a recording after too little **actual spoken audio**, because it counted part of the early silence before the learner started speaking.

That made the transcription service receive audio that was technically long enough overall, but still had too little useful speech near the actual speaking portion.

## Why it looked like “only the first second” was transcribed

The shared recorder lives in:

- [AudioRecorder.vue](../resources/js/Components/Learner/AudioRecorder.vue)

This recorder is reused across several learner voice tasks, so a timing bug there could affect multiple diagnostic steps, not just one page.

Before the fix, the recorder had two behaviors that combined badly:

1. recording started immediately
2. the learner often waited a moment before speaking

There was also a cue delay before the learner was really expected to speak.

### What went wrong

The old minimum-duration logic treated the recording as long enough based on total elapsed time, not on actual spoken time after the cue.

So this kind of sequence could happen:

1. recording starts
2. the learner waits briefly
3. the learner says only a short sound or starts late
4. the system accepts the recording because enough total time has passed

The result is:

- the saved recording may contain a lot of leading silence
- the useful speech portion may be very short
- STT can collapse the result into something tiny like one letter or one partial sound

That is why the symptom felt like “it only transcribed the first second” even when the file itself was longer than one second.

## Why this could affect the whole diagnostic

This was not only a Task 2B problem.

Because the recorder component is shared, the timing/silence issue could affect any diagnostic step that depended on voice upload and transcription, especially:

- Task 1 letter pronunciation
- Task 2B word-in-sentence
- passage reading

The exact symptom could differ by task:

- letters might come back as partial or wrong sounds
- highlighted-word tasks might come back as only one letter
- passage reading might come back as a very short fragment, or no useful transcript at all

## Why some screenshots looked worse than the timing bug alone

Some later passage screenshots showed:

- empty transcript
- the whole passage highlighted red
- upload or save failure messages

Those cases were sometimes a **different bug** on top of the timing issue.

For passage reading, there was also a backend save failure at one point, so:

- sometimes the problem was bad/partial speech capture
- sometimes the problem was the transcript never being saved at all

So the “first second only” symptom and the “upload failed” symptom were related to the same area of the product, but they were not always the exact same root cause.

## The actual underlying cause

The old recorder logic effectively mixed together:

- pre-speech silence
- cue delay
- actual speech duration

That meant the minimum recording gate could be satisfied before enough real spoken material had been captured for reliable transcription.

## Fix direction that was applied

The recorder behavior was adjusted so the minimum-duration logic is based much more on the learner's spoken portion rather than just total elapsed recording time.

The recorder was also updated to trim leading and trailing silence before upload so the speech starts closer to the beginning of the audio sent for transcription.

That improved the STT input for short utterances and reduced the chance of getting only:

- one letter
- one syllable
- one clipped fragment

## Main takeaway

The old bug looked like “the system only transcribes the first second,” but the more precise explanation is:

- the recorder sometimes accepted audio with too much leading silence and too little real speech
- because the recorder is shared, the issue could show up across multiple diagnostic voice tasks
- later passage failures were sometimes a separate save/upload problem, not only the timing problem

## Related docs

- [TASK2B_STATE_AND_RESUME_BUG.md](./TASK2B_STATE_AND_RESUME_BUG.md)
