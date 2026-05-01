# Laravel AI Integration Contract

Laravel calls the FastAPI ASR service and does not run ASR inference locally.

## Request Fields

ASR requests should include audio plus available scoring context:

- `expected_text`
- `prompt_type`
- `activity_type`
- `module_type`
- `assessment_type`
- `item_id`
- `learner_id`
- `attempt_id`
- `current_scoring_context`
- `content_metadata`

## Response Fields

Laravel consumes Wav2Vec2-only ASR responses with these fields when present:

- `raw_transcript`
- `wav2vec2_transcript`
- `corrected_transcript`
- `displayed_transcript`
- `expected_text`
- `prompt_type`
- `asr_route`
- `model_family`
- `model_used`
- WER/CER fields
- phoneme fields
- acceptance flags
- normalization/correction metadata
- `debug_metadata`

Missing optional fields are safe.

## Transcript Selection

Scoring uses `corrected_transcript`, then `transcript`, then `raw_transcript`.

Learner UI uses `displayed_transcript`, then `corrected_transcript`, then `transcript`, then `raw_transcript`.

Admin/debug views preserve raw and corrected/displayed transcript metadata.
