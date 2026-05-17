# Task 2B Transcription Audit

Date: May 17, 2026

Route audited: `http://127.0.0.1:8000/learner/diagnostic/task-2b`

I audited the local Laravel/Vue code, the saved diagnostic attempt data, the audio file records, the Laravel log, and the ReaDirect AI/ASR health endpoint. I did not inspect the live browser tab directly.

## Short Answer

Task 2B transcription is not failing because the AI service is down. The AI service is running and reports Wav2Vec2 ASR as available.

The main problems are:

1. Task 2B audio is often being transcribed as fragments or nearby words.
2. Several recordings have too little speech or too much silence.
3. The AI service cannot find the content item for Task 2B audio and falls back to request-generated enrichment.
4. Laravel's fallback STT provider is configured as `mock` with an empty transcript.
5. Task 2B uses two different task type labels in different places, which makes debugging and content matching harder.

## Current Configuration

From the local app config:

| Setting | Value |
| --- | --- |
| `READIRECT_AI_ENABLED` | `true` |
| AI base URL | `http://127.0.0.1:8001` |
| AI fallback to existing STT | `true` |
| STT provider | `mock` |
| Mock transcript | empty |

From the AI health endpoint:

| AI Health Field | Value |
| --- | --- |
| Status | `ok` |
| ASR provider | `wav2vec2_only` |
| Active ASR model | `wav2vec2` |
| Wav2Vec2 available | `true` |
| Whisper available | `false` |
| Content index loaded | `false` |
| Supported prompt types | `letter`, `word`, `sentence` |
| Expected-centric scoring | `true` |
| Audio quality validation | `true` |

## Evidence From Recent Attempts

The two latest diagnostic attempts with Task 2B data were:

| Attempt | Status | Task 2B Score | Updated |
| --- | --- | ---: | --- |
| 18 | `crla_completed` | 6 | 2026-05-17 02:31:13 |
| 19 | `module_placement_completed` | 3 | 2026-05-10 12:51:29 |

### Attempt 18

This is the most recent completed CRLA attempt. Several items were correct, but several were transcribed as close fragments.

| Item | Expected Word | Saved Transcript | Score | Speech / Silence Ratio |
| --- | --- | --- | ---: | --- |
| 1 | `sun` | `fun` | 4.4 | 0.51s / 0.819 |
| 2 | `fish` | `fish` | 10 | 0.51s / 0.782 |
| 3 | `dog` | `dog` | 10 | 0.51s / 0.819 |
| 4 | `moon` | `moon` | 10 | 0.69s / 0.765 |
| 5 | `bee` | `a b is on the flower` | 1.2 | 1.71s / 0.548 |
| 6 | `book` | `bock` | 4.5 | 0.45s / 0.858 |
| 7 | `bed` | `bd` | 1.6 | 0.84s / 0.813 |
| 8 | `hat` | `blue` | 0 | 0.60s / 0.811 |
| 9 | `car` | `car` | 10 | 0.63s / 0.744 |
| 10 | `ball` | `bal` | 4.7 | 0.57s / 0.768 |

Every saved row above also had these AI warnings:

```text
content_item_not_found
enrichment_generated_from_request
```

### Attempt 19

This older attempt shows stronger fragment behavior:

| Item | Expected Word | Saved Transcript | Score |
| --- | --- | --- | ---: |
| 1 | `ball` | `bol` | 0 |
| 2 | `tree` | `trtr` | 0 |
| 3 | `cat` | `catgot` | 0 |
| 4 | `sun` | `son` | 0 |
| 5 | `fish` | `fish` | 8.5 |
| 6 | `hand` | `hand` | 8.5 |
| 7 | `dog` | `do` | 0 |
| 8 | `bug` | `bug` | 8.5 |
| 9 | `bee` | `bbbbb` | 0 |
| 10 | `hat` | `ha` | 0 |

This attempt also had the same `content_item_not_found` and `enrichment_generated_from_request` warnings on every saved Task 2B row.

## Rejected Audio Before Submission

Several Task 2B recordings were rejected before becoming saved answers.

Recent examples:

| Audio ID | Attempt | Item | Transcript | Speech | Silence Ratio | Error |
| --- | ---: | --- | --- | ---: | ---: | --- |
| 812 | 18 | 301 | `b` | 0.51s | 0.84 | `Let us answer this first.` |
| 811 | 18 | 301 | `b` | 0.51s | 0.84 | `Let us answer this first.` |
| 810 | 18 | 301 | `b` | 0.96s | 0.673 | `Let us answer this first.` |
| 809 | 18 | 301 | `b` | 0.84s | 0.75 | `Let us answer this first.` |
| 808 | 18 | 301 | `b` | 0.84s | 0.75 | `Let us answer this first.` |
| 782 | 19 | 287 | empty | unknown | unknown | `Please record again. Make sure your voice is clear and close enough to the microphone.` |
| 781 | 19 | 287 | empty | unknown | unknown | `Please record again. Make sure your voice is clear and close enough to the microphone.` |
| 780 | 19 | 287 | empty | unknown | unknown | `Please record again. Make sure your voice is clear and close enough to the microphone.` |

The attempt 19 rejected rows included the AI warning `mostly_silent`.

## Code Findings

### 1. Task 2B has two task type names

Frontend upload:

`resources/js/Pages/Learner/Task2BWordInSentence.vue`

```js
payload.append('task_type', 'crla_task_2b_sentence');
```

Stored diagnostic items and responses:

`app/Services/AssessmentItemSelectionService.php`

```php
public const TASK_2B_WORD_SENTENCE = 'task_2b_word_in_sentence';
```

Laravel handles this partly inside `AudioUploadController`, but the split matters because audio metadata stores `crla_task_2b_sentence` while response rows store `task_2b_word_in_sentence`. That can make content lookup, logs, filters, and audit queries disagree unless every layer knows both names.

### 2. Expected text is target-word based for Task 2B uploads

`app/Http/Controllers/Learner/AudioUploadController.php` sets Task 2B `expected_text` and `expected_answer` to the highlighted target word:

```php
$expectedAnswer = $taskType === 'crla_task_2b_sentence'
    ? ($payload['target_word'] ?? $payload['expected_answer'] ?? $snapshot['prompt'] ?? null)
    : ($payload['expected_answer'] ?? $payload['target_word'] ?? $snapshot['prompt'] ?? null);
```

This is correct for "read the highlighted word" behavior, but it means the ASR/scoring system is checking short words like `sun`, `bee`, `bed`, `hat`, and `ball`. Short words are much easier for ASR to confuse with fragments or near-sounding words.

### 3. Content item enrichment is not loading

The AI health endpoint reports:

```text
content_index_loaded: false
```

And every recent Task 2B audio row reports:

```text
content_item_not_found
enrichment_generated_from_request
```

This means the AI service is not finding the content item by ID. It still analyzes the audio using the request payload, but richer content metadata is missing. This also matches the saved scoring metadata where fields such as `target_word`, `actual_target_word`, `target_word_phoneme_similarity_percentage`, and `target_word_error_type` are null.

### 4. Fallback STT is not a useful fallback right now

`config/stt.php` uses:

```php
'provider' => env('STT_PROVIDER', env('READIRECT_STT_PROVIDER', 'mock')),
```

The current app config resolves to:

```text
stt_provider: mock
stt_mock_transcript: ""
```

`AIAnalysisResolver` calls AI first. If AI does not provide a usable transcript, it can fall back to the configured STT provider. Because that provider is currently `mock` with an empty transcript, the fallback path cannot rescue failed Task 2B audio.

### 5. There is at least one missing expected-text log

Laravel logged:

```text
[2026-05-10 12:31:44] local.WARNING: ASR request is missing expected_text; expected-centric scoring will be disabled in AI. {"activity_type":null,"task_type":null,"item_id":null,"attempt_id":19}
```

This warning is important because expected-centric scoring is what lets the ASR judge a short answer against the highlighted word. When `expected_text` is missing, the model loses that target.

## Most Likely Causes

1. **Recording quality and timing.** Many accepted recordings contain less than 1 second of detected speech and high silence ratios. That produces weak ASR evidence for short words.
2. **Short target words.** Words like `bee`, `bed`, `hat`, `ball`, and `sun` are easy to collapse into fragments such as `b`, `bd`, `ha`, or `bal`.
3. **Missing AI content index.** `content_index_loaded=false` and `content_item_not_found` appear consistently, so the AI is operating with less context than intended.
4. **Fallback STT is empty.** If AI rejects or cannot confidently transcribe audio, the Laravel fallback is currently not useful.
5. **Task type naming drift.** `crla_task_2b_sentence` and `task_2b_word_in_sentence` both refer to Task 2B, but different systems see different labels.

## Recommended Fixes

1. Load or rebuild the AI service content index so Task 2B items can be found by ID.
2. Standardize Task 2B task type naming, or add a single normalization helper so both `crla_task_2b_sentence` and `task_2b_word_in_sentence` resolve to one canonical value before AI payload creation.
3. Configure a real fallback STT provider or disable fallback expectations when `STT_PROVIDER=mock` and `STT_MOCK_TRANSCRIPT` is empty.
4. Improve the recorder UX for Task 2B:
   - wait until speech starts before counting the minimum duration,
   - warn when speech duration is under 1 second,
   - show a clearer "too much silence" message before upload,
   - trim leading silence before sending to ASR.
5. Store the full AI response on Task 2B response rows, or at least store `expected_text`, `prompt_type`, `audio_quality`, and warning fields. The audio rows currently have the important AI warnings, but some response rows do not retain enough raw AI payload to debug later.
6. Add a Task 2B regression test that uploads a short recording with a known expected word and asserts:
   - `expected_text` is present,
   - `prompt_type` is `word` or the intended Task 2B mode,
   - content lookup succeeds,
   - fallback behavior is explicit when AI fails.

## Bottom Line

The Task 2B page is sending recordings, and the AI service is responding. The transcription problems are coming from a combination of short/silent audio, missing content enrichment, empty fallback STT, and naming drift between frontend upload metadata and stored diagnostic task types.
